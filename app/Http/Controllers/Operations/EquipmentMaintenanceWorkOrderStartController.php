<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentMaintenance;
use App\Http\Requests\Operations\Equipment\Maintenance\StartMaintenanceWorkOrderRequest;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMaintenanceWorkOrderStartController
{
    public function __invoke(StartMaintenanceWorkOrderRequest $request, EquipmentMaintenanceWorkOrder $equipmentMaintenanceWorkOrder, ManageEquipmentMaintenance $action): RedirectResponse
    {
        Gate::authorize('start', $equipmentMaintenanceWorkOrder);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->start($equipmentMaintenanceWorkOrder, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Maintenance started; equipment is unavailable for assignment.']);

        return back();
    }
}
