<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryReservationStatus;
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
