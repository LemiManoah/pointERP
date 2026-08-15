<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\AssignEquipment;
use App\Http\Requests\Operations\EquipmentAssignments\StoreEquipmentAssignmentRequest;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentAssignmentController
{
    public function store(StoreEquipmentAssignmentRequest $request, Equipment $equipment, AssignEquipment $action): RedirectResponse
    {
        Gate::authorize('view', $equipment);
        Gate::authorize('create', EquipmentAssignment::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($equipment, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment handed over and assigned.']);

        return to_route('equipment.show', ['equipment' => $equipment, 'tab' => 'assignments']);
    }
}
