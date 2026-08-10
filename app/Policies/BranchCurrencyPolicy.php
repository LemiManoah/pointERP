<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BranchCurrency;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class BranchCurrencyPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('currency-settings.manage');
    }

    public function view(User $user, BranchCurrency $branchCurrency): bool
    {
        return $this->belongsToSameTenant($user, $branchCurrency->tenant_id)
            && $this->canAccessBranch($user, $branchCurrency->branch_id)
            && $user->can('currency-settings.manage');
    }

    public function update(User $user, BranchCurrency $branchCurrency): bool
    {
        return $this->view($user, $branchCurrency);
    }
}
