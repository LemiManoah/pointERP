<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryTrackingType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryReservation;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryQuantityConverter;
use App\Services\InventoryStockBalance;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PostInventoryStockMovement
{
    public function __construct(private TenantContext $tenantContext, private InventoryStockBalance $balances, private InventoryQuantityConverter $converter, private AuditLogger $auditLogger) {}

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
            $multiplier = isset($data['conversion_multiplier'])
                ? BigDecimal::of((string) $data['conversion_multiplier'])
                : $this->converter->multiplier($item, (string) $data['original_unit_id']);
            $stockQuantity = $original->multipliedBy($multiplier);
            $signedQuantity = match ($type) {
                InventoryMovementType::Issue, InventoryMovementType::TransferOut => $stockQuantity->negated(),
                InventoryMovementType::Adjustment => $data['adjustment_direction'] === 'decrease' ? $stockQuantity->negated() : $stockQuantity,
                default => $stockQuantity,
            };

            $this->validateBatch($item, $data['inventory_batch_id'] ?? null);
            $balance = $this->balances->for($store, $item);
            $current = BigDecimal::of($balance['on_hand']);
            if ($type === InventoryMovementType::Issue) {
                $spendable = BigDecimal::of($balance['available']);
                $reservationId = $data['reservation_id'] ?? null;
                if (is_string($reservationId)) {
                    $reservation = InventoryReservation::query()->lockForUpdate()->find($reservationId);
                    if (! $reservation instanceof InventoryReservation || $reservation->inventory_store_id !== $store->id || $reservation->inventory_item_id !== $item->id) {
                        throw ValidationException::withMessages(['reservation' => 'The requisition reservation is not valid for this stock issue.']);
                    }

                    $spendable = $spendable
                        ->plus((string) $reservation->reserved_quantity)
                        ->minus((string) $reservation->issued_quantity)
                        ->minus((string) $reservation->released_quantity);
                }

                if ($stockQuantity->isGreaterThan($spendable)) {
                    throw ValidationException::withMessages(['original_quantity' => 'Only '.$spendable->toScale(4).' stock units are available for this issue after reservations.']);
                }
            }

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

    private function validateBatch(InventoryItem $item, mixed $batchId): void
    {
        if ($item->tracking_type !== InventoryTrackingType::Batch) {
            return;
        }

        if (! is_string($batchId) || $batchId === '') {
            throw ValidationException::withMessages(['inventory_batch_id' => 'Select a batch for this batch-tracked item.']);
        }

        $valid = InventoryBatch::query()->whereKey($batchId)->where('inventory_item_id', $item->id)->where('is_active', true)->exists();
        if (! $valid) {
            throw ValidationException::withMessages(['inventory_batch_id' => 'The selected batch is not available for this item and store.']);
        }
    }
}
