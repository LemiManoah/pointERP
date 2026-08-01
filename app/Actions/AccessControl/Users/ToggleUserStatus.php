<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Users;

use App\Models\User;

final class ToggleUserStatus
{
    public function handle(User $user): User
    {
        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        return $user;
    }
}
