<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private array $roles = [
        'Director' => [
            'access-control.users.manage',
            'access-control.roles.manage',
            'audit-trail.view',
            'branch-users.manage',
            'branches.view',
            'branches.view-all',
            'currency-settings.manage',
            'exchange-rates.approve',
            'exchange-rates.create',
            'exchange-rates.update',
            'exchange-rates.view',
            'foundation.countries.manage',
            'foundation.currencies.manage',
            'operations.projects.manage',
            'operations.reports.approve',
            'resources.staff.manage',
            'tenants.update',
        ],
        'Administrator' => [
            'access-control.users.manage',
            'branch-users.manage',
            'branches.view',
            'currency-settings.manage',
            'exchange-rates.create',
            'exchange-rates.update',
            'exchange-rates.view',
            'foundation.countries.manage',
            'foundation.currencies.manage',
            'resources.staff.manage',
        ],
        'Project Manager' => [
            'branches.view',
            'operations.projects.manage',
            'operations.reports.approve',
        ],
        'Accountant' => [
            'branches.view',
            'exchange-rates.create',
            'exchange-rates.update',
            'exchange-rates.view',
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
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

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

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
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
