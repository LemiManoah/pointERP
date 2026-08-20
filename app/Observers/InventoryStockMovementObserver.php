<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InventoryStockMovement;
use LogicException;

final class InventoryStockMovementObserver
{
    public function updating(InventoryStockMovement $movement): void
    {
        $mutable = ['status', 'reversed_by', 'reversed_at', 'updated_at'];
        $changed = array_keys($movement->getDirty());
        throw_if(array_diff($changed, $mutable) !== [], LogicException::class, 'Posted stock movement details are immutable. Post a reversal instead.');
    }

    public function deleting(): never
    {
        throw new LogicException('Posted stock movements cannot be deleted. Post a reversal instead.');
    }
}
