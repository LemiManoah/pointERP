<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryPriceTier;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class InventoryPriceTierPermanentDeleteController
{
    public function __invoke(InventoryPriceTier $inventoryPriceTier, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('forceDelete', $inventoryPriceTier);
        throw_if($inventoryPriceTier->is_active, ValidationException::withMessages(['price_list' => 'Deactivate the price list before permanently deleting it.']));
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        try {
            DB::transaction(function () use ($actor, $auditLogger, $inventoryPriceTier): void {
                $auditLogger->record('inventory.price_list.permanently_deleted', $inventoryPriceTier, $actor, $inventoryPriceTier->toArray());
                $inventoryPriceTier->forceDelete();
            });
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Price list permanently deleted.']);
        } catch (QueryException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This price list cannot be deleted because item prices use it.']);
        }

        return to_route('inventory.index', ['tab' => 'price-lists', 'status' => 'inactive']);
    }
}
