<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryStoreItem;
use App\Http\Requests\Operations\Inventory\StoreInventoryStoreItemRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStoreItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryStoreItemController
{
    public function store(StoreInventoryStoreItemRequest $request, InventoryItem $inventoryItem, SaveInventoryStoreItem $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Store item settings saved.']);

        return to_route('inventory.items.show', $inventoryItem);
    }

    public function update(StoreInventoryStoreItemRequest $request, InventoryItem $inventoryItem, InventoryStoreItem $inventoryStoreItem, SaveInventoryStoreItem $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        abort_unless($inventoryStoreItem->inventory_item_id === $inventoryItem->id, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor, $inventoryStoreItem);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Store item settings updated.']);

        return to_route('inventory.items.show', $inventoryItem);
    }
}
