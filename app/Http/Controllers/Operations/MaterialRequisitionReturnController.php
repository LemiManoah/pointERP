<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReturnMaterialRequisitionLine;
use App\Http\Requests\Operations\Inventory\ReturnMaterialRequisitionLineRequest;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class MaterialRequisitionReturnController
{
    public function __invoke(ReturnMaterialRequisitionLineRequest $request, MaterialRequisition $materialRequisition, MaterialRequisitionLine $materialRequisitionLine, ReturnMaterialRequisitionLine $action): RedirectResponse
    {
        Gate::authorize('returnStock', $materialRequisition);
        abort_unless($materialRequisitionLine->material_requisition_id === $materialRequisition->id, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($materialRequisition, $materialRequisitionLine, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unused stock returned to the requisition store.']);

        return to_route('inventory.requisitions.show', $materialRequisition);
    }
}
