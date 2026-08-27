<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ManagePurchaseOrder;
use App\Http\Requests\Operations\Inventory\ReviewPurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class PurchaseOrderReviewController
{
    public function __invoke(ReviewPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, ManagePurchaseOrder $action): RedirectResponse
    {
        Gate::authorize('approve', $purchaseOrder);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $data = $request->validated();
        $action->review($purchaseOrder, (string) $data['decision'], isset($data['reason']) ? (string) $data['reason'] : null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order decision recorded.']);

        return back();
    }
}
