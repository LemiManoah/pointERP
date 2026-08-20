<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryTrackingType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\InventoryUnitConversion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryStockBalance;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PostInventoryStockMovement
{
    public function __construct(private TenantContext $tenantContext, private InventoryStockBalance $balances, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(InventoryStore $store, InventoryItem $item, array $data, User $actor): InventoryStockMovement
    {
        return DB::transaction(function () use ($actor, $data, $item, $store): InventoryStockMovement {
            $storeItem = InventoryStoreItem::query()->where('inventory_store_id', $store->id)->where('inventory_item_id', $item->id)->lockForUpdate()->first();
            if (! $storeItem instanceof InventoryStoreItem || ! $storeItem->is_active) {
                throw ValidationException::withMessages(['inventory_item_id' => 'Enable this item in the selected store before posting stock.']);
            }

            $existing = InventoryStockMovement::query()->where('source_key', $data['source_key'])->first();
            if ($existing instanceof InventoryStockMovement) {
                if ($existing->inventory_store_id !== $store->id || $existing->inventory_item_id !== $item->id) {
                    throw ValidationException::withMessages(['source_key' => 'This posting key is already used by another stock movement.']);
                }

                return $existing;
            }

            $type = InventoryMovementType::from((string) $data['movement_type']);
            $original = BigDecimal::of((string) $data['original_quantity']);
            $multiplier = $this->conversionMultiplier($item, (string) $data['original_unit_id']);
            $stockQuantity = $original->multipliedBy($multiplier);
            $signedQuantity = match ($type) {
                InventoryMovementType::Issue, InventoryMovementType::TransferOut => $stockQuantity->negated(),
                InventoryMovementType::Adjustment => $data['adjustment_direction'] === 'decrease' ? $stockQuantity->negated() : $stockQuantity,
                default => $stockQuantity,
            };

            $this->validateBatch($item, $store, $data['inventory_batch_id'] ?? null);
            $current = BigDecimal::of($this->balances->for($store, $item)['on_hand']);
            if ($current->plus($signedQuantity)->isNegative()) {
                throw ValidationException::withMessages(['original_quantity' => 'This movement would create negative stock. Available on-hand quantity is '.$current->toScale(4).'.']);
            }

            $movement = InventoryStockMovement::query()->create([
                'tenant_id' => $this->tenantContext->id(), 'branch_id' => $store->branch_id,
                'inventory_store_id' => $store->id, 'inventory_item_id' => $item->id,
                'inventory_batch_id' => $data['inventory_batch_id'] ?? null,
                'movement_type' => $type, 'status' => InventoryMovementStatus::Posted,
                'quantity' => (string) $signedQuantity->toScale(4, RoundingMode::HalfUp), 'original_quantity' => (string) $original->toScale(4, RoundingMode::HalfUp),
                'original_unit_id' => $data['original_unit_id'], 'conversion_multiplier' => (string) $multiplier->toScale(10),
                'source_type' => $data['source_type'] ?? 'manual', 'source_id' => $data['source_id'] ?? null,
                'source_key' => $data['source_key'], 'project_id' => $data['project_id'] ?? null,
                'site_id' => $data['site_id'] ?? null, 'equipment_id' => $data['equipment_id'] ?? null,
                'reason' => $data['reason'], 'posted_by' => $actor->id, 'posted_at' => now(),
            ]);
            $this->auditLogger->record('inventory.stock.posted', $movement, $actor, [], $movement->toArray(), (string) $data['reason'], $store->branch);

            return $movement;
        });
    }

    private function conversionMultiplier(InventoryItem $item, string $unitId): BigDecimal
    {
        if ($unitId === $item->stock_unit_id) {
            return BigDecimal::one();
        }

        $conversion = InventoryUnitConversion::query()->where('inventory_item_id', $item->id)->where('from_unit_id', $unitId)->where('to_unit_id', $item->stock_unit_id)->where('is_active', true)->first();
        if (! $conversion instanceof InventoryUnitConversion) {
            throw ValidationException::withMessages(['original_unit_id' => 'No active conversion exists from this unit to the item stock unit.']);
        }

        return BigDecimal::of($conversion->multiplier)->dividedBy($conversion->divisor, 10, RoundingMode::HalfUp);
    }

    private function validateBatch(InventoryItem $item, InventoryStore $store, mixed $batchId): void
    {
        if ($item->tracking_type !== InventoryTrackingType::Batch) {
            return;
        }

        if (! is_string($batchId) || $batchId === '') {
            throw ValidationException::withMessages(['inventory_batch_id' => 'Select a batch for this batch-tracked item.']);
        }

        $valid = InventoryBatch::query()->whereKey($batchId)->where('inventory_item_id', $item->id)->where(fn (Builder $query): Builder => $query->whereNull('inventory_store_id')->orWhere('inventory_store_id', $store->id))->where('is_active', true)->exists();
        if (! $valid) {
            throw ValidationException::withMessages(['inventory_batch_id' => 'The selected batch is not available for this item and store.']);
        }
    }
}
