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

final class PurchaseOrderSubmissionController
{
    public function __invoke(Request $request, PurchaseOrder $purchaseOrder, ManagePurchaseOrder $action): RedirectResponse
    {
        Gate::authorize('submit', $purchaseOrder);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->submit($purchaseOrder, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order submitted for approval.']);

        return back();
    }
}
