<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ReturnEquipment;
use App\Http\Requests\Operations\EquipmentAssignments\ReturnEquipmentAssignmentRequest;
use App\Models\EquipmentAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentAssignmentReturnController
{
    public function __invoke(ReturnEquipmentAssignmentRequest $request, EquipmentAssignment $equipmentAssignment, ReturnEquipment $action): RedirectResponse
    {
        Gate::authorize('update', $equipmentAssignment);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $assignment = $action->handle($equipmentAssignment, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment return accepted.']);

        return to_route('equipment.show', ['equipment' => $assignment->equipment_id, 'tab' => 'assignments']);
    }
}
