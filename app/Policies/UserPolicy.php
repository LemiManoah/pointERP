<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class UserPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('access-control.users.manage');
    }

    public function view(User $user, User $model): bool
    {
        $branchIds = $user->branches()->pluck('branches.id')->all();

        return $this->belongsToSameTenant($user, $model->tenant_id)
            && $user->can('access-control.users.manage')
            && ($user->can('branches.view-all') || $model->branches()->whereIn('branches.id', $branchIds)->exists());
    }

    public function create(User $user): bool
    {
        return $user->can('access-control.users.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->view($user, $model) && $user->id !== $model->id;
    }
}
