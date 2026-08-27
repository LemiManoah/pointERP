<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReviewInventoryTransfer;
use App\Models\InventoryTransfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class InventoryTransferApprovalController
{
    public function __invoke(InventoryTransfer $inventoryTransfer, ReviewInventoryTransfer $action): RedirectResponse
    {
        Gate::authorize('approve', $inventoryTransfer);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        try {
            $action->approve($inventoryTransfer, $actor);
        } catch (ValidationException $validationException) {
            $messages = array_values($validationException->errors());
            Inertia::flash('toast', ['type' => 'error', 'message' => $messages[0][0] ?? 'The transfer could not be approved.']);

            return back()->withErrors($validationException->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer approved and stock balances updated.']);

        return back();
    }
}
