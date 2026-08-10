<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class StaffPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('resources.staff.manage');
    }

    public function view(User $user, Staff $staff): bool
    {
        return $this->belongsToSameTenant($user, $staff->tenant_id)
            && $this->canAccessBranch($user, $staff->branch_id)
            && $user->can('resources.staff.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('resources.staff.manage');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $this->view($user, $staff);
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $this->view($user, $staff);
    }
}
