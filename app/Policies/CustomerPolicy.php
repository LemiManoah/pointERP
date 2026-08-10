<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class CustomerPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('customers.view')) {
            return true;
        }

        return $user->can('customers.manage');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->belongsToSameTenant($user, $customer->tenant_id)
            && $this->canAccessBranch($user, $customer->branch_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->belongsToSameTenant($user, $customer->tenant_id)
            && $this->canAccessBranch($user, $customer->branch_id)
            && $user->can('customers.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }
}
