<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryBatch;
use App\Http\Requests\Operations\Inventory\StoreInventoryBatchRequest;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryBatchController
{
    public function store(StoreInventoryBatchRequest $request, InventoryItem $inventoryItem, SaveInventoryBatch $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory batch saved.']);

        return to_route('inventory.items.show', $inventoryItem);
    }

    public function update(StoreInventoryBatchRequest $request, InventoryItem $inventoryItem, InventoryBatch $inventoryBatch, SaveInventoryBatch $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        abort_unless($inventoryBatch->inventory_item_id === $inventoryItem->id, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor, $inventoryBatch);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory batch updated.']);

        return to_route('inventory.items.show', $inventoryItem);
    }
}
