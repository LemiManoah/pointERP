<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Branch;
use App\Models\User;

trait ChecksTenantAccess
{
    private function belongsToSameTenant(User $user, string $tenantId): bool
    {
        return $user->tenant_id === $tenantId;
    }

    private function canAccessBranch(User $user, ?string $branchId): bool
    {
        if ($branchId === null) {
            return $user->can('branches.view-all');
        }

        if ($user->can('branches.view-all')) {
            return true;
        }

        return $user->branches()->whereKey($branchId)->exists();
    }

    private function canAccessBranchModel(User $user, Branch $branch): bool
    {
        return $this->belongsToSameTenant($user, $branch->tenant_id)
            && $this->canAccessBranch($user, $branch->id);
    }
}
