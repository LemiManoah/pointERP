<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class InventoryCategoryPermanentDeleteController
{
    public function __invoke(InventoryCategory $inventoryCategory, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('forceDelete', $inventoryCategory);
        throw_if($inventoryCategory->is_active, ValidationException::withMessages(['category' => 'Deactivate the category before permanently deleting it.']));
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        try {
            DB::transaction(function () use ($actor, $auditLogger, $inventoryCategory): void {
                $auditLogger->record('inventory.category.permanently_deleted', $inventoryCategory, $actor, $inventoryCategory->toArray());
                $inventoryCategory->forceDelete();
            });
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Category permanently deleted.']);
        } catch (QueryException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This category cannot be deleted because inventory items use it.']);
        }

        return to_route('inventory.index', ['tab' => 'categories', 'status' => 'inactive']);
    }
}
