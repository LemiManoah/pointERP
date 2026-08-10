<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Roles;

use App\Models\Role;
use App\Services\AuditLogger;

final readonly class CreateRole
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /**
     * @param  array{name: string, permissions?: list<string>}  $data
     */
    public function handle(array $data): Role
    {
        $role = Role::query()->create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        $role->load('permissions');

        $this->auditLogger->record(
            event: 'access.role.created',
            subject: $role,
            newValues: [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
        );

        return $role;
    }
}
