<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Users;

use App\Models\User;
use App\Services\AuditLogger;

final readonly class ToggleUserStatus
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(User $user): User
    {
        $oldValues = ['is_active' => $user->is_active];

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        $this->auditLogger->record(
            event: $user->is_active ? 'access.user.activated' : 'access.user.deactivated',
            subject: $user,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return $user;
    }
}
