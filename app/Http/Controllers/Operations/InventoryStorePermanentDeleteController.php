<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryStore;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class InventoryStorePermanentDeleteController
{
    public function __invoke(InventoryStore $inventoryStore, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('forceDelete', $inventoryStore);
        throw_if($inventoryStore->is_active, ValidationException::withMessages(['store' => 'Deactivate the store before permanently deleting it.']));
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        try {
            DB::transaction(function () use ($actor, $auditLogger, $inventoryStore): void {
                $auditLogger->record('inventory.store.permanently_deleted', $inventoryStore, $actor, $inventoryStore->toArray());
                $inventoryStore->forceDelete();
            });
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Store permanently deleted.']);
        } catch (QueryException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This store cannot be deleted because inventory or operational records use it.']);
        }

        return to_route('inventory.index', ['tab' => 'stores', 'status' => 'inactive']);
    }
}
