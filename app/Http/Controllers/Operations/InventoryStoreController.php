<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryStore;
use App\Http\Requests\Operations\Inventory\StoreInventoryStoreRequest;
use App\Models\InventoryStore;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryStoreController
{
    public function store(StoreInventoryStoreRequest $request, SaveInventoryStore $action): RedirectResponse
    {
        Gate::authorize('create', InventoryStore::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Store saved.']);

        return to_route('inventory.index', ['tab' => 'stores']);
    }

    public function update(StoreInventoryStoreRequest $request, InventoryStore $inventoryStore, SaveInventoryStore $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryStore);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $inventoryStore);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Store updated.']);

        return to_route('inventory.index', ['tab' => 'stores']);
    }
}
