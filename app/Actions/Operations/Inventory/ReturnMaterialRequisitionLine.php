<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\User;
use App\Services\AuditLogger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReturnMaterialRequisitionLine
{
    public function __construct(private PostInventoryStockMovement $postMovement, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(MaterialRequisition $requisition, MaterialRequisitionLine $line, array $data, User $actor): MaterialRequisition
    {
        return DB::transaction(function () use ($actor, $data, $line, $requisition): MaterialRequisition {
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            $line = MaterialRequisitionLine::query()->lockForUpdate()->findOrFail($line->id);
            if ($line->material_requisition_id !== $requisition->id) {
                throw ValidationException::withMessages(['line' => 'The selected line does not belong to this requisition.']);
            }

            $item = InventoryItem::query()->find($line->inventory_item_id);
            if (! $item instanceof InventoryItem) {
                throw ValidationException::withMessages(['line' => 'Only an inventory item can be returned to stock.']);
            }

            $existing = InventoryStockMovement::query()->where('source_key', $data['source_key'])->first();
            if ($existing instanceof InventoryStockMovement) {
                if ($existing->source_type !== MaterialRequisitionLine::class || $existing->source_id !== $line->id) {
                    throw ValidationException::withMessages(['source_key' => 'This return request has already been used for another stock movement.']);
                }

                return $requisition;
            }

            $original = BigDecimal::of((string) $data['quantity']);
            $stockQuantity = $original->multipliedBy((string) $line->conversion_multiplier);
            $returnable = BigDecimal::of((string) $line->issued_quantity)->minus((string) $line->returned_quantity);
            if ($stockQuantity->isGreaterThan($returnable)) {
                throw ValidationException::withMessages(['quantity' => 'The return exceeds the net issued quantity of '.$returnable->toScale(4).' stock units.']);
            }

            $this->postMovement->handle($requisition->store, $item, [
                'movement_type' => InventoryMovementType::Return->value,
                'original_quantity' => (string) $original,
                'original_unit_id' => $line->unit_of_measure_id,
                'conversion_multiplier' => $line->conversion_multiplier,
                'inventory_batch_id' => $data['inventory_batch_id'] ?? null,
                'source_type' => MaterialRequisitionLine::class,
                'source_id' => $line->id,
                'source_key' => $data['source_key'],
                'project_id' => $requisition->project_id,
                'site_id' => $requisition->site_id,
                'reason' => $data['reason'],
            ], $actor);

            $previousReturned = (string) $line->returned_quantity;
            $returned = BigDecimal::of($previousReturned)->plus($stockQuantity);
            $line->forceFill(['returned_quantity' => (string) $returned->toScale(4, RoundingMode::HalfUp)])->save();
            $this->auditLogger->record('inventory.requisition.stock_returned', $line, $actor, ['returned_quantity' => $previousReturned], ['returned_quantity' => (string) $returned->toScale(4)], (string) $data['reason'], $requisition->branch);

            return $requisition->refresh();
        });
    }
}
