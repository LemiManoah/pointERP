<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SubmitMaterialRequisition;
use App\Models\MaterialRequisition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class MaterialRequisitionSubmissionController
{
    public function __invoke(Request $request, MaterialRequisition $materialRequisition, SubmitMaterialRequisition $action): RedirectResponse
    {
        Gate::authorize('submit', $materialRequisition);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($materialRequisition, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Material requisition submitted for approval.']);

        return to_route('inventory.requisitions.show', $materialRequisition);
    }
}
