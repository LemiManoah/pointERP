<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Roles;

use App\Models\Role;

final class CreateRole
{
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

        return $role;
    }
}
