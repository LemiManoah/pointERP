<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class BranchPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('branches.view')) {
            return true;
        }

        return $user->can('branches.view-all');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->canAccessBranchModel($user, $branch)
            && ($user->can('branches.view') || $user->can('branches.view-all'));
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
