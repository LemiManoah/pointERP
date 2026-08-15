<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentAssignment;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentAssignmentPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('equipment.view');
    }

    public function view(User $user, EquipmentAssignment $equipmentAssignment): bool
    {
        return $this->belongsToSameTenant($user, $equipmentAssignment->tenant_id)
            && $this->canAccessBranch($user, $equipmentAssignment->branch_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('equipment.assignments.manage');
    }

    public function update(User $user, EquipmentAssignment $equipmentAssignment): bool
    {
        return $this->view($user, $equipmentAssignment)
            && $equipmentAssignment->status === EquipmentAssignment::STATUS_ACTIVE
            && $user->can('equipment.assignments.manage');
    }
}
