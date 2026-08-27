<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReviewMaterialRequisition;
use App\Http\Requests\Operations\Inventory\ReviewMaterialRequisitionRequest;
use App\Models\MaterialRequisition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class MaterialRequisitionReviewController
{
    public function __invoke(ReviewMaterialRequisitionRequest $request, MaterialRequisition $materialRequisition, ReviewMaterialRequisition $action): RedirectResponse
    {
        Gate::authorize('approve', $materialRequisition);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $decision = (string) $request->validated('decision');
        $action->handle($materialRequisition, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => match ($decision) { 'approve' => 'Material requisition approved and stock reserved.', 'return' => 'Material requisition returned for revision.', default => 'Material requisition rejected.' }]);

        return to_route('inventory.requisitions.show', $materialRequisition);
    }
}
