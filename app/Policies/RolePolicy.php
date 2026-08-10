<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('access-control.roles.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('access-control.roles.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('access-control.roles.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('access-control.roles.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('access-control.roles.manage');
    }
}
