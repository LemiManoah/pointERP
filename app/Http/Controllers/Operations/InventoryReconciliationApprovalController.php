<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReviewInventoryReconciliation;
use App\Models\InventoryReconciliation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class InventoryReconciliationApprovalController
{
    public function __invoke(InventoryReconciliation $inventoryReconciliation, ReviewInventoryReconciliation $action): RedirectResponse
    {
        Gate::authorize('approve', $inventoryReconciliation);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        try {
            $action->approve($inventoryReconciliation, $actor);
        } catch (ValidationException $validationException) {
            $messages = array_values($validationException->errors());
            Inertia::flash('toast', ['type' => 'error', 'message' => $messages[0][0] ?? 'The reconciliation could not be approved.']);

            return back()->withErrors($validationException->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reconciliation approved and variance recorded.']);

        return back();
    }
}
