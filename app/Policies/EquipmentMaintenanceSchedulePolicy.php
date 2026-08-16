<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentMaintenanceSchedule;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentMaintenanceSchedulePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool { return $user->can('equipment.view'); }

    public function view(User $user, EquipmentMaintenanceSchedule $schedule): bool
    {
        return $this->belongsToSameTenant($user, $schedule->tenant_id)
            && $this->canAccessBranch($user, $schedule->branch_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool { return $user->can('equipment.maintenance.manage'); }

    public function update(User $user, EquipmentMaintenanceSchedule $schedule): bool
    {
        return $this->view($user, $schedule) && $user->can('equipment.maintenance.manage');
    }
}
