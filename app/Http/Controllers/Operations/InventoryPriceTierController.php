<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryPriceTier;
use App\Http\Requests\Operations\Inventory\StoreInventoryPriceTierRequest;
use App\Models\InventoryPriceTier;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryPriceTierController
{
    public function store(StoreInventoryPriceTierRequest $request, SaveInventoryPriceTier $action): RedirectResponse
    {
        Gate::authorize('create', InventoryPriceTier::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Price list created.']);

        return to_route('inventory.index', ['tab' => 'price-lists']);
    }

    public function update(StoreInventoryPriceTierRequest $request, InventoryPriceTier $inventoryPriceTier, SaveInventoryPriceTier $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryPriceTier);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $inventoryPriceTier);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Price list updated.']);

        return to_route('inventory.index', ['tab' => 'price-lists']);
    }

    public function destroy(InventoryPriceTier $inventoryPriceTier, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('update', $inventoryPriceTier);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $old = ['is_active' => $inventoryPriceTier->is_active];
        $inventoryPriceTier->update(['is_active' => ! $inventoryPriceTier->is_active, 'updated_by' => $actor->id]);
        $auditLogger->record('inventory.price_list.status_changed', $inventoryPriceTier, $actor, $old, ['is_active' => $inventoryPriceTier->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $inventoryPriceTier->is_active ? 'Price list restored.' : 'Price list deactivated.']);

        return to_route('inventory.index', ['tab' => 'price-lists', 'status' => $inventoryPriceTier->is_active ? 'active' : 'inactive']);
    }
}
