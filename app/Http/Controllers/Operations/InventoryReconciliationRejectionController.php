<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReviewInventoryReconciliation;
use App\Models\InventoryReconciliation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryReconciliationRejectionController
{
    public function __invoke(Request $request, InventoryReconciliation $inventoryReconciliation, ReviewInventoryReconciliation $action): RedirectResponse
    {
        Gate::authorize('reject', $inventoryReconciliation);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->reject($inventoryReconciliation, (string) $data['reason'], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reconciliation rejected.']);

        return back();
    }
}
