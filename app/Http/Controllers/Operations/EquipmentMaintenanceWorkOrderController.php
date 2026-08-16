<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentMaintenance;
use App\Http\Requests\Operations\Equipment\Maintenance\StoreMaintenanceWorkOrderRequest;
use App\Models\Equipment;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMaintenanceWorkOrderController
{
    public function store(StoreMaintenanceWorkOrderRequest $request, Equipment $equipment, ManageEquipmentMaintenance $action): RedirectResponse
    {
        Gate::authorize('create', EquipmentMaintenanceWorkOrder::class);
        Gate::authorize('view', $equipment);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->createWorkOrder($equipment, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Maintenance work order submitted for approval.']);

        return to_route('equipment.show', ['equipment' => $equipment, 'tab' => 'maintenance']);
    }
}
