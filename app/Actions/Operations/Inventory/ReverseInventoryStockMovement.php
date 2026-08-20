<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStoreItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryStockBalance;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReverseInventoryStockMovement
{
    public function __construct(private InventoryStockBalance $balances, private AuditLogger $auditLogger) {}

    public function handle(InventoryStockMovement $movement, User $actor, string $reason): InventoryStockMovement
    {
        return DB::transaction(function () use ($actor, $movement, $reason): InventoryStockMovement {
            $movement = InventoryStockMovement::query()->with(['store.branch', 'item'])->lockForUpdate()->findOrFail($movement->id);
            if ($movement->reversed_at !== null || $movement->movement_type === InventoryMovementType::Reversal) {
                throw ValidationException::withMessages(['movement' => 'This movement is already a reversal or has already been reversed.']);
            }

            InventoryStoreItem::query()->where('inventory_store_id', $movement->inventory_store_id)->where('inventory_item_id', $movement->inventory_item_id)->lockForUpdate()->firstOrFail();
            $reversalQuantity = BigDecimal::of($movement->quantity)->negated();
            $current = BigDecimal::of($this->balances->for($movement->store, $movement->item)['on_hand']);
            if ($current->plus($reversalQuantity)->isNegative()) {
                throw ValidationException::withMessages(['movement' => 'This reversal would create negative stock because later stock has already been consumed.']);
            }

            $reversal = InventoryStockMovement::query()->create([
                'tenant_id' => $movement->tenant_id, 'branch_id' => $movement->branch_id,
                'inventory_store_id' => $movement->inventory_store_id, 'inventory_item_id' => $movement->inventory_item_id,
                'inventory_batch_id' => $movement->inventory_batch_id, 'movement_type' => InventoryMovementType::Reversal,
                'status' => InventoryMovementStatus::Posted, 'quantity' => (string) $reversalQuantity->toScale(4, RoundingMode::HalfUp),
                'original_quantity' => $movement->original_quantity, 'original_unit_id' => $movement->original_unit_id,
                'conversion_multiplier' => $movement->conversion_multiplier, 'source_type' => InventoryStockMovement::class,
                'source_id' => $movement->id, 'source_key' => 'reversal:'.$movement->id, 'reversal_of_id' => $movement->id,
                'project_id' => $movement->project_id, 'site_id' => $movement->site_id, 'equipment_id' => $movement->equipment_id,
                'reason' => $reason, 'posted_by' => $actor->id, 'posted_at' => now(),
            ]);
            $movement->update(['status' => InventoryMovementStatus::Reversed, 'reversed_by' => $actor->id, 'reversed_at' => now()]);
            $this->auditLogger->record('inventory.stock.reversed', $movement, $actor, ['status' => InventoryMovementStatus::Posted->value], ['status' => InventoryMovementStatus::Reversed->value, 'reversal_id' => $reversal->id], $reason, $movement->store->branch);

            return $reversal;
        });
    }
}
