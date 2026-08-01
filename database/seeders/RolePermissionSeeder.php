<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private array $roles = [
        'Director' => [
            'access-control.users.manage',
            'access-control.roles.manage',
            'foundation.countries.manage',
            'foundation.currencies.manage',
            'operations.projects.manage',
            'operations.reports.approve',
            'resources.staff.manage',
        ],
        'Administrator' => [
            'access-control.users.manage',
            'foundation.countries.manage',
            'foundation.currencies.manage',
            'resources.staff.manage',
        ],
        'Project Manager' => [
            'operations.projects.manage',
            'operations.reports.approve',
        ],
        'Accountant' => [
            'finance.payments.manage',
            'finance.reports.view',
        ],
        'Store Keeper' => [
            'inventory.stock.manage',
            'procurement.requests.view',
        ],
    ];

    public function run(): void
    {
        foreach ($this->permissions() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach ($this->roles as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->givePermissionTo($permissions);
        }
    }

    /**
     * @return list<string>
     */
    private function permissions(): array
    {
        return collect($this->roles)
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
