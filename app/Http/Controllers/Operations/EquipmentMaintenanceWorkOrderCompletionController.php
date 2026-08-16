<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentMaintenance;
use App\Http\Requests\Operations\Equipment\Maintenance\CompleteMaintenanceWorkOrderRequest;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMaintenanceWorkOrderCompletionController
{
    public function __invoke(CompleteMaintenanceWorkOrderRequest $request, EquipmentMaintenanceWorkOrder $equipmentMaintenanceWorkOrder, ManageEquipmentMaintenance $action): RedirectResponse
    {
        Gate::authorize('complete', $equipmentMaintenanceWorkOrder);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->complete($equipmentMaintenanceWorkOrder, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Maintenance completed and equipment released.']);

        return back();
    }
}
