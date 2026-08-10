<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Roles;

use App\Models\Role;
use App\Services\AuditLogger;

final readonly class UpdateRole
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /**
     * @param  array{name: string, permissions?: list<string>}  $data
     */
    public function handle(Role $role, array $data): Role
    {
        $role->load('permissions');
        $oldValues = [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ];

        $role->update([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        $role->load('permissions');

        $this->auditLogger->record(
            event: 'access.role.updated',
            subject: $role,
            oldValues: $oldValues,
            newValues: [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
        );

        return $role;
    }
}
