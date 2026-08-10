<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class TenantPolicy
{
    use ChecksTenantAccess;

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->belongsToSameTenant($user, $tenant->id);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->belongsToSameTenant($user, $tenant->id)
            && $user->can('tenants.update');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }
}
