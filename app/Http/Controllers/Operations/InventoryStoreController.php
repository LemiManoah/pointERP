<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryStore;
use App\Http\Requests\Operations\Inventory\StoreInventoryStoreRequest;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\AuditLogger;
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

    public function destroy(InventoryStore $inventoryStore, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $inventoryStore);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $old = ['is_active' => $inventoryStore->is_active];
        $inventoryStore->update(['is_active' => ! $inventoryStore->is_active, 'updated_by' => $actor->id]);
        $auditLogger->record('inventory.store.status_changed', $inventoryStore, $actor, $old, ['is_active' => $inventoryStore->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $inventoryStore->is_active ? 'Store restored.' : 'Store deactivated.']);

        return to_route('inventory.index', ['tab' => 'stores']);
    }
}
