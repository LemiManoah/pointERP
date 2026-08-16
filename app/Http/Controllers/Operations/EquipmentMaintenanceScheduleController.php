<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentMaintenance;
use App\Http\Requests\Operations\Equipment\Maintenance\StoreMaintenanceScheduleRequest;
use App\Models\Equipment;
use App\Models\EquipmentMaintenanceSchedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMaintenanceScheduleController
{
    public function store(StoreMaintenanceScheduleRequest $request, Equipment $equipment, ManageEquipmentMaintenance $action): RedirectResponse
    {
        Gate::authorize('view', $equipment);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->saveSchedule($equipment, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Maintenance schedule created.']);

        return to_route('equipment.show', ['equipment' => $equipment, 'tab' => 'maintenance']);
    }

    public function update(StoreMaintenanceScheduleRequest $request, Equipment $equipment, EquipmentMaintenanceSchedule $equipmentMaintenanceSchedule, ManageEquipmentMaintenance $action): RedirectResponse
    {
        Gate::authorize('update', $equipmentMaintenanceSchedule);
        abort_unless($equipmentMaintenanceSchedule->equipment_id === $equipment->id, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->saveSchedule($equipment, $request->validated(), $actor, $equipmentMaintenanceSchedule);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Maintenance schedule updated.']);

        return back();
    }
}
