<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\IssueMaterialRequisitionLine;
use App\Http\Requests\Operations\Inventory\IssueMaterialRequisitionLineRequest;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class MaterialRequisitionIssueController
{
    public function __invoke(IssueMaterialRequisitionLineRequest $request, MaterialRequisition $materialRequisition, MaterialRequisitionLine $materialRequisitionLine, IssueMaterialRequisitionLine $action): RedirectResponse
    {
        Gate::authorize('issue', $materialRequisition);
        abort_unless($materialRequisitionLine->material_requisition_id === $materialRequisition->id, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($materialRequisition, $materialRequisitionLine, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock issued against the requisition line.']);

        return to_route('inventory.requisitions.show', $materialRequisition);
    }
}
