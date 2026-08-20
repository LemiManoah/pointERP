<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryItem;
use App\Http\Requests\Operations\Inventory\StoreInventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryItemController
{
    public function store(StoreInventoryItemRequest $request, SaveInventoryItem $action): RedirectResponse
    {
        Gate::authorize('create', InventoryItem::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item saved.']);

        return to_route('inventory.index');
    }

    public function update(StoreInventoryItemRequest $request, InventoryItem $inventoryItem, SaveInventoryItem $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $inventoryItem);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item updated.']);

        return to_route('inventory.index');
    }
}
