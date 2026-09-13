<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StaffDeployment;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class StaffDeploymentPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('workforce.view')) {
            return true;
        }

        return $user->can('workforce.deployments.manage');
    }

    public function view(User $user, StaffDeployment $deployment): bool
    {
        return $this->belongsToSameTenant($user, $deployment->tenant_id)
            && $this->canAccessBranch($user, $deployment->branch_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('workforce.deployments.manage');
    }

    public function update(User $user, StaffDeployment $deployment): bool
    {
        return $this->view($user, $deployment) && $this->create($user);
    }
}
