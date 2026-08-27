<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryReservationStatus;
use App\Enums\MaterialRequisitionStatus;
use App\Models\InventoryItem;
use App\Models\InventoryReservation;
use App\Models\InventoryStockMovement;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\User;
use App\Services\AuditLogger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class IssueMaterialRequisitionLine
{
    public function __construct(private PostInventoryStockMovement $postMovement, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(MaterialRequisition $requisition, MaterialRequisitionLine $line, array $data, User $actor): MaterialRequisition
    {
        return DB::transaction(function () use ($actor, $data, $line, $requisition): MaterialRequisition {
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            $line = MaterialRequisitionLine::query()->lockForUpdate()->findOrFail($line->id);
            if ($line->material_requisition_id !== $requisition->id || ! in_array($requisition->status, [MaterialRequisitionStatus::Approved, MaterialRequisitionStatus::PartiallyIssued], true)) {
                throw ValidationException::withMessages(['requisition' => 'This requisition line is not available for issue.']);
            }

            $item = InventoryItem::query()->find($line->inventory_item_id);
            if (! $item instanceof InventoryItem) {
                throw ValidationException::withMessages(['line' => 'Link this requisition line to an inventory item before issuing stock.']);
            }

            $reservation = InventoryReservation::query()->where('source_type', MaterialRequisitionLine::class)->where('source_id', $line->id)->lockForUpdate()->first();
            if (! $reservation instanceof InventoryReservation) {
                throw ValidationException::withMessages(['line' => 'This line has no active stock reservation.']);
            }

            $existing = InventoryStockMovement::query()->where('source_key', $data['source_key'])->first();
            if ($existing instanceof InventoryStockMovement) {
                if ($existing->source_type !== MaterialRequisitionLine::class || $existing->source_id !== $line->id) {
                    throw ValidationException::withMessages(['source_key' => 'This issue request has already been used for another stock movement.']);
                }

                return $requisition;
            }

            $original = BigDecimal::of((string) $data['quantity']);
            $stockQuantity = $original->multipliedBy((string) $line->conversion_multiplier);
            $outstanding = BigDecimal::of((string) $line->approved_quantity)->minus((string) $line->issued_quantity);
            if ($stockQuantity->isGreaterThan($outstanding)) {
                throw ValidationException::withMessages(['quantity' => 'The issue exceeds the approved outstanding quantity of '.$outstanding->toScale(4).' stock units.']);
            }

            $this->postMovement->handle($requisition->store, $item, [
                'movement_type' => InventoryMovementType::Issue->value,
                'original_quantity' => (string) $original,
                'original_unit_id' => $line->unit_of_measure_id,
                'conversion_multiplier' => $line->conversion_multiplier,
                'inventory_batch_id' => $data['inventory_batch_id'] ?? null,
                'reservation_id' => $reservation->id,
                'source_type' => MaterialRequisitionLine::class,
                'source_id' => $line->id,
                'source_key' => $data['source_key'],
                'project_id' => $requisition->project_id,
                'site_id' => $requisition->site_id,
                'reason' => $data['reason'],
            ], $actor);

            $previousIssued = (string) $line->issued_quantity;
            $issued = BigDecimal::of($previousIssued)->plus($stockQuantity);
            $line->forceFill(['issued_quantity' => (string) $issued->toScale(4, RoundingMode::HalfUp)])->save();
            $reservation->forceFill(['issued_quantity' => (string) $issued->toScale(4, RoundingMode::HalfUp), 'status' => $issued->isGreaterThanOrEqualTo((string) $reservation->reserved_quantity) ? InventoryReservationStatus::Fulfilled : InventoryReservationStatus::PartiallyIssued, 'updated_by' => $actor->id])->save();
            $this->refreshStatus($requisition, $actor);
            $this->auditLogger->record('inventory.requisition.stock_issued', $line, $actor, ['issued_quantity' => $previousIssued], ['issued_quantity' => (string) $issued->toScale(4)], (string) $data['reason'], $requisition->branch);

            return $requisition->refresh();
        });
    }

    private function refreshStatus(MaterialRequisition $requisition, User $actor): void
    {
        $lines = $requisition->lines()->get(['approved_quantity', 'issued_quantity']);
        $fulfilled = $lines->every(fn (MaterialRequisitionLine $line): bool => BigDecimal::of((string) $line->issued_quantity)->isGreaterThanOrEqualTo((string) $line->approved_quantity));
        $requisition->forceFill(['status' => $fulfilled ? MaterialRequisitionStatus::Fulfilled : MaterialRequisitionStatus::PartiallyIssued, 'updated_by' => $actor->id])->save();
    }
}
