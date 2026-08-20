<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryUnitConversion;
use App\Http\Requests\Operations\Inventory\StoreInventoryUnitConversionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryUnitConversion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryUnitConversionController
{
    public function store(StoreInventoryUnitConversionRequest $request, InventoryItem $inventoryItem, SaveInventoryUnitConversion $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit conversion saved.']);

        return to_route('inventory.items.show', $inventoryItem);
    }

    public function update(StoreInventoryUnitConversionRequest $request, InventoryItem $inventoryItem, InventoryUnitConversion $inventoryUnitConversion, SaveInventoryUnitConversion $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        abort_unless($inventoryUnitConversion->inventory_item_id === $inventoryItem->id, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor, $inventoryUnitConversion);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit conversion updated.']);

        return to_route('inventory.items.show', $inventoryItem);
    }
}
