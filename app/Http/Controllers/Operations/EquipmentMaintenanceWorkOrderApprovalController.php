<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentMaintenance;
use App\Http\Requests\Operations\Equipment\Maintenance\ApproveMaintenanceWorkOrderRequest;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMaintenanceWorkOrderApprovalController
{
    public function __invoke(ApproveMaintenanceWorkOrderRequest $request, EquipmentMaintenanceWorkOrder $equipmentMaintenanceWorkOrder, ManageEquipmentMaintenance $action): RedirectResponse
    {
        Gate::authorize('approve', $equipmentMaintenanceWorkOrder);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->approve($equipmentMaintenanceWorkOrder, $request->string('approval_note')->value() ?: null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Maintenance work order approved.']);

        return back();
    }
}
