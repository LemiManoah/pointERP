<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ManagePurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class PurchaseOrderCloseController
{
    public function __invoke(Request $request, PurchaseOrder $purchaseOrder, ManagePurchaseOrder $action): RedirectResponse
    {
        Gate::authorize('close', $purchaseOrder);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->close($purchaseOrder, (string) $data['reason'], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order closed with its remaining quantity cancelled.']);

        return back();
    }
}
