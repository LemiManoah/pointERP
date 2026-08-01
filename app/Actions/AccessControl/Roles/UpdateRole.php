<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Roles;

use App\Models\Role;

final class UpdateRole
{
    /**
     * @param  array{name: string, permissions?: list<string>}  $data
     */
    public function handle(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return $role;
    }
}
