<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryUnitConversion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class InventoryQuantityConverter
{
    public function multiplier(InventoryItem $item, string $unitId): BigDecimal
    {
        if ($unitId === $item->stock_unit_id) {
            return BigDecimal::one();
        }

        $conversion = InventoryUnitConversion::query()
            ->where('inventory_item_id', $item->id)
            ->where('from_unit_id', $unitId)
            ->where('to_unit_id', $item->stock_unit_id)
            ->where('is_active', true)
            ->first();

        if ($conversion instanceof InventoryUnitConversion) {
            return BigDecimal::of((string) $conversion->multiplier)
                ->dividedBy((string) $conversion->divisor, 10, RoundingMode::HalfUp);
        }

        $reverse = InventoryUnitConversion::query()
            ->where('inventory_item_id', $item->id)
            ->where('from_unit_id', $item->stock_unit_id)
            ->where('to_unit_id', $unitId)
            ->where('is_active', true)
            ->first();

        if ($reverse instanceof InventoryUnitConversion) {
            return BigDecimal::of((string) $reverse->divisor)
                ->dividedBy((string) $reverse->multiplier, 10, RoundingMode::HalfUp);
        }

        throw ValidationException::withMessages(['unit_of_measure_id' => 'No active conversion exists between this unit and the item stock unit.']);
    }
}
