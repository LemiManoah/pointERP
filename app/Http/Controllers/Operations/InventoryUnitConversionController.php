<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryUnitConversion;
use App\Http\Requests\Operations\Inventory\StoreInventoryUnitConversionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryUnitConversion;
use App\Models\User;
use App\Services\AuditLogger;
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

        return $request->validated('return_to') === 'register'
            ? to_route('inventory.index', ['tab' => 'conversions'])
            : to_route('inventory.items.show', $inventoryItem);
    }

    public function update(StoreInventoryUnitConversionRequest $request, InventoryItem $inventoryItem, InventoryUnitConversion $inventoryUnitConversion, SaveInventoryUnitConversion $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        abort_unless($inventoryUnitConversion->inventory_item_id === $inventoryItem->id, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $inventoryItem, $actor, $inventoryUnitConversion);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit conversion updated.']);

        return $request->validated('return_to') === 'register'
            ? to_route('inventory.index', ['tab' => 'conversions'])
            : to_route('inventory.items.show', $inventoryItem);
    }

    public function destroy(InventoryItem $inventoryItem, InventoryUnitConversion $inventoryUnitConversion, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        abort_unless($inventoryUnitConversion->inventory_item_id === $inventoryItem->id, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $old = ['is_active' => $inventoryUnitConversion->is_active];
        $inventoryUnitConversion->update(['is_active' => ! $inventoryUnitConversion->is_active]);
        $auditLogger->record('inventory.unit_conversion.status_changed', $inventoryUnitConversion, $actor, $old, ['is_active' => $inventoryUnitConversion->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $inventoryUnitConversion->is_active ? 'Unit conversion restored.' : 'Unit conversion deactivated.']);

        return to_route('inventory.index', ['tab' => 'conversions', 'status' => $inventoryUnitConversion->is_active ? 'active' : 'inactive']);
    }
}
