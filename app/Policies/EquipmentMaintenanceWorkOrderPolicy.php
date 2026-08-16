<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentMaintenanceWorkOrderPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool { return $user->can('equipment.view'); }

    public function view(User $user, EquipmentMaintenanceWorkOrder $workOrder): bool
    {
        return $this->belongsToSameTenant($user, $workOrder->tenant_id)
            && $this->canAccessBranch($user, $workOrder->branch_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('equipment.maintenance.request') || $user->can('equipment.maintenance.manage');
    }

    public function approve(User $user, EquipmentMaintenanceWorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder)
            && $workOrder->status === EquipmentMaintenanceWorkOrder::STATUS_PLANNED
            && $workOrder->requested_by !== $user->id
            && $user->can('equipment.maintenance.approve');
    }

    public function start(User $user, EquipmentMaintenanceWorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder)
            && $workOrder->status === EquipmentMaintenanceWorkOrder::STATUS_APPROVED
            && $user->can('equipment.maintenance.manage');
    }

    public function complete(User $user, EquipmentMaintenanceWorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder)
            && $workOrder->status === EquipmentMaintenanceWorkOrder::STATUS_IN_PROGRESS
            && $user->can('equipment.maintenance.manage');
    }

    public function cancel(User $user, EquipmentMaintenanceWorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder)
            && in_array($workOrder->status, [EquipmentMaintenanceWorkOrder::STATUS_PLANNED, EquipmentMaintenanceWorkOrder::STATUS_APPROVED, EquipmentMaintenanceWorkOrder::STATUS_IN_PROGRESS], true)
            && $user->can('equipment.maintenance.manage');
    }
}
