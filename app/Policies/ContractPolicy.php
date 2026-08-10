<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class ContractPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('contracts.view')) {
            return true;
        }

        return $user->can('contracts.manage');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $this->belongsToSameTenant($user, $contract->tenant_id)
            && $this->canAccessBranch($user, $contract->branch_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('contracts.manage');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->belongsToSameTenant($user, $contract->tenant_id)
            && $this->canAccessBranch($user, $contract->branch_id)
            && $user->can('contracts.manage');
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->update($user, $contract);
    }
}
