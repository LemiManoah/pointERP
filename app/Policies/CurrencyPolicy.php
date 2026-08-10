<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('foundation.currencies.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('foundation.currencies.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('foundation.currencies.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('foundation.currencies.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('foundation.currencies.manage');
    }
}
