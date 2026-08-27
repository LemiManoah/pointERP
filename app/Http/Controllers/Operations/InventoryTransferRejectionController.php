<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReviewInventoryTransfer;
use App\Models\InventoryTransfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryTransferRejectionController
{
    public function __invoke(Request $request, InventoryTransfer $inventoryTransfer, ReviewInventoryTransfer $action): RedirectResponse
    {
        Gate::authorize('reject', $inventoryTransfer);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->reject($inventoryTransfer, (string) $data['reason'], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer rejected.']);

        return back();
    }
}
