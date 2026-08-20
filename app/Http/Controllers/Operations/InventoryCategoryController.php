<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryCategory;
use App\Http\Requests\Operations\Inventory\StoreInventoryCategoryRequest;
use App\Models\InventoryCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryCategoryController
{
    public function store(StoreInventoryCategoryRequest $request, SaveInventoryCategory $action): RedirectResponse
    {
        Gate::authorize('create', InventoryCategory::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory category saved.']);

        return to_route('inventory.index', ['tab' => 'categories']);
    }

    public function update(StoreInventoryCategoryRequest $request, InventoryCategory $inventoryCategory, SaveInventoryCategory $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryCategory);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $inventoryCategory);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory category updated.']);

        return to_route('inventory.index', ['tab' => 'categories']);
    }

    public function destroy(InventoryCategory $inventoryCategory, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $inventoryCategory);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $old = ['is_active' => $inventoryCategory->is_active];
        $inventoryCategory->update(['is_active' => ! $inventoryCategory->is_active, 'updated_by' => $actor->id]);
        $auditLogger->record('inventory.category.status_changed', $inventoryCategory, $actor, $old, ['is_active' => $inventoryCategory->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $inventoryCategory->is_active ? 'Category restored.' : 'Category deactivated.']);

        return to_route('inventory.index', ['tab' => 'categories']);
    }
}
