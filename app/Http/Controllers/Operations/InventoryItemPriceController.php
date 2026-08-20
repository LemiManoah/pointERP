<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryItemPrice;
use App\Http\Requests\Operations\Inventory\StoreInventoryItemPriceRequest;
use App\Models\InventoryItem;
use App\Models\InventoryItemPrice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryItemPriceController
{
    public function store(StoreInventoryItemPriceRequest $request, InventoryItem $inventoryItem, SaveInventoryItemPrice $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        Gate::authorize('viewCosts', InventoryItem::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Price list entry saved.']);

        return to_route('inventory.items.show', $inventoryItem);
    }

    public function update(StoreInventoryItemPriceRequest $request, InventoryItem $inventoryItem, InventoryItemPrice $inventoryItemPrice, SaveInventoryItemPrice $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        Gate::authorize('viewCosts', InventoryItem::class);
        abort_unless($inventoryItemPrice->inventory_item_id === $inventoryItem->id, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor, $inventoryItemPrice);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Price list entry updated.']);

        return to_route('inventory.items.show', $inventoryItem);
    }
}
