<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryReservationStatus;
use App\Enums\InventoryBatchStatus;
use App\Enums\InventoryTrackingType;
use App\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Builder;
use App\Models\InventoryItem;
use App\Models\InventoryReservation;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

final class InventoryStockBalance
{
    /** @return array{on_hand: string, reserved: string, available: string} */
    public function for(InventoryStore $store, InventoryItem $item): array
    {
        /** @var Collection<int, InventoryStockMovement> $movements */
        $movements = InventoryStockMovement::query()->where('inventory_store_id', $store->id)->where('inventory_item_id', $item->id)->get(['quantity']);
        $onHand = $movements->reduce(fn (BigDecimal $total, InventoryStockMovement $movement): BigDecimal => $total->plus((string) $movement->quantity), BigDecimal::zero());

        /** @var Collection<int, InventoryReservation> $reservations */
        $reservations = InventoryReservation::query()->where('inventory_store_id', $store->id)->where('inventory_item_id', $item->id)->whereIn('status', [InventoryReservationStatus::Active->value, InventoryReservationStatus::PartiallyIssued->value])->get();
        $reserved = $reservations->reduce(fn (BigDecimal $total, InventoryReservation $reservation): BigDecimal => $total->plus((string) $reservation->reserved_quantity)->minus((string) $reservation->issued_quantity)->minus((string) $reservation->released_quantity), BigDecimal::zero());

        return [
            'on_hand' => (string) $onHand->toScale(4),
            'reserved' => (string) $reserved->toScale(4),
            'available' => (string) $onHand->minus($reserved)->toScale(4),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, InventoryBatch> */
    public function sellableBatches(InventoryStore $store, InventoryItem $item): \Illuminate\Database\Eloquent\Collection
    {
        return InventoryBatch::query()->where('tenant_id', $store->tenant_id)->where('inventory_item_id', $item->id)
            ->whereIn('id', InventoryStockMovement::query()->select('inventory_batch_id')
                ->where('tenant_id', $store->tenant_id)->where('inventory_store_id', $store->id)->where('inventory_item_id', $item->id)
                ->whereNotNull('inventory_batch_id')->groupBy('inventory_batch_id')->havingRaw('SUM(quantity) > 0'))
            ->where('status', InventoryBatchStatus::Available)->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
            ->orderByRaw('expires_on IS NULL')->oldest('expires_on')->oldest()->orderBy('id')->get();
    }

    public function availableForPos(InventoryStore $store, InventoryItem $item): string
    {
        $available = BigDecimal::of($this->for($store, $item)['available']);
        if ($item->tracking_type === InventoryTrackingType::Batch) {
            $batchTotal = BigDecimal::zero();
            foreach ($this->sellableBatches($store, $item) as $batch) {
                $batchTotal = $batchTotal->plus($this->forBatch($store, $item, $batch->id));
            }
            if ($batchTotal->isLessThan($available)) {
                $available = $batchTotal;
            }
        }

        return (string) ($available->isNegative() ? BigDecimal::zero() : $available)->toScale(4);
    }

    public function forBatch(InventoryStore $store, InventoryItem $item, string $batchId): string
    {
        /** @var Collection<int, InventoryStockMovement> $movements */
        $movements = InventoryStockMovement::query()
            ->where('inventory_store_id', $store->id)
            ->where('inventory_item_id', $item->id)
            ->where('inventory_batch_id', $batchId)
            ->get(['quantity']);

        return (string) $movements
            ->reduce(fn (BigDecimal $total, InventoryStockMovement $movement): BigDecimal => $total->plus((string) $movement->quantity), BigDecimal::zero())
            ->toScale(4);
    }
}
