<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\InventoryItemPrice;
use App\Models\InventoryPriceTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class PosPriceResolver
{
    /** @return array{id: string|null, amount: string} */
    public function resolve(InventoryItem $item, InventoryPriceTier $tier, Branch $branch, string $unitId): array
    {
        $price = $this->price($item, $tier, $branch->id, $unitId)
            ?? $this->price($item, $tier, null, $unitId);

        if ($price instanceof InventoryItemPrice) {
            return ['id' => $price->id, 'amount' => (string) $price->amount];
        }

        if ($unitId === $item->stock_unit_id && $item->default_selling_price !== null) {
            return ['id' => null, 'amount' => (string) $item->default_selling_price];
        }

        throw ValidationException::withMessages([
            'price' => 'Configure an active selling price for '.$item->name.' in the selected unit and price list.',
        ]);
    }

    private function price(InventoryItem $item, InventoryPriceTier $tier, ?string $branchId, string $unitId): ?InventoryItemPrice
    {
        return InventoryItemPrice::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_price_tier_id', $tier->id)
            ->where('unit_of_measure_id', $unitId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', today()))
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))
            ->first();
    }
}
