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
            'contracts.manage',
            'contracts.view',
            'currency-settings.manage',
            'customers.manage',
            'customers.view',
            'daily-site-reports.approve',
            'daily-site-reports.create',
            'daily-site-reports.return',
            'daily-site-reports.review',
            'daily-site-reports.submit',
            'daily-site-reports.update',
            'daily-site-reports.view',
            'dashboards.projects.view',
            'exchange-rates.approve',
            'exchange-rates.create',
            'exchange-rates.update',
            'exchange-rates.view',
            'foundation.countries.manage',
            'foundation.currencies.manage',
            'operations.projects.manage',
            'operations.reports.approve',
            'project-activities.manage',
            'project-users.manage',
            'projects.archive',
            'projects.create',
            'projects.update',
            'projects.view',
            'projects.view-all',
            'resources.staff.manage',
            'site-users.manage',
            'sites.archive',
            'sites.create',
            'sites.update',
            'sites.view',
            'sites.view-all',
            'tenants.update',
        ],
        'Administrator' => [
            'access-control.users.manage',
            'branch-users.manage',
            'branches.view',
            'contracts.manage',
            'contracts.view',
            'currency-settings.manage',
            'customers.manage',
            'customers.view',
            'daily-site-reports.create',
            'daily-site-reports.return',
            'daily-site-reports.review',
            'daily-site-reports.submit',
            'daily-site-reports.update',
            'daily-site-reports.view',
            'dashboards.projects.view',
            'exchange-rates.create',
            'exchange-rates.update',
            'exchange-rates.view',
            'foundation.countries.manage',
            'foundation.currencies.manage',
            'project-activities.manage',
            'project-users.manage',
            'projects.archive',
            'projects.create',
            'projects.update',
            'projects.view',
            'resources.staff.manage',
            'site-users.manage',
            'sites.archive',
            'sites.create',
            'sites.update',
            'sites.view',
        ],
        'Project Manager' => [
            'branches.view',
            'contracts.view',
            'customers.view',
            'daily-site-reports.approve',
            'daily-site-reports.create',
            'daily-site-reports.return',
            'daily-site-reports.review',
            'daily-site-reports.submit',
            'daily-site-reports.update',
            'daily-site-reports.view',
            'dashboards.projects.view',
            'operations.projects.manage',
            'operations.reports.approve',
            'projects.update',
            'projects.view',
            'site-users.manage',
            'sites.create',
            'sites.update',
            'sites.view',
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
        'Site Manager' => [
            'branches.view',
            'customers.view',
            'daily-site-reports.create',
            'daily-site-reports.submit',
            'daily-site-reports.update',
            'daily-site-reports.view',
            'projects.view',
            'sites.view',
        ],
        'Auditor' => [
            'audit-trail.view',
            'branches.view',
            'contracts.view',
            'customers.view',
            'daily-site-reports.view',
            'dashboards.projects.view',
            'projects.view',
            'sites.view',
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
