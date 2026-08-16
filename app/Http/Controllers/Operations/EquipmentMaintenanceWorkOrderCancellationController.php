<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentMaintenance;
use App\Http\Requests\Operations\Equipment\Maintenance\CancelMaintenanceWorkOrderRequest;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMaintenanceWorkOrderCancellationController
{
    public function __invoke(CancelMaintenanceWorkOrderRequest $request, EquipmentMaintenanceWorkOrder $equipmentMaintenanceWorkOrder, ManageEquipmentMaintenance $action): RedirectResponse
    {
        Gate::authorize('cancel', $equipmentMaintenanceWorkOrder);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->cancel($equipmentMaintenanceWorkOrder, $request->string('reason')->value(), $request->string('release_status')->value(), $actor);
        Inertia::flash('toast', ['type' => 'warning', 'message' => 'Maintenance work order cancelled.']);

        return back();
    }
}
