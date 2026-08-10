<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StaffPosition;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class StaffPositionPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('resources.staff.manage');
    }

    public function view(User $user, StaffPosition $staffPosition): bool
    {
        return $this->belongsToSameTenant($user, $staffPosition->tenant_id)
            && $user->can('resources.staff.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('resources.staff.manage');
    }

    public function update(User $user, StaffPosition $staffPosition): bool
    {
        return $this->view($user, $staffPosition);
    }

    public function delete(User $user, StaffPosition $staffPosition): bool
    {
        return $this->view($user, $staffPosition);
    }
}
