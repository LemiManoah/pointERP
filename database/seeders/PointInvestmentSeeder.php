<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\EnsureDefaultTenant;
use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\ExchangeRate;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffPosition;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

final class PointInvestmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = resolve(EnsureDefaultTenant::class)->handle();
        resolve(TenantContext::class)->set($tenant);

        $branch = Branch::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'KLA-HQ'],
            [
                'name' => 'Kampala Head Office',
                'country_code' => 'UG',
                'default_currency_code' => 'UGX',
                'status' => 'active',
            ],
        );

        $directorPosition = StaffPosition::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DIRECTOR'],
            [
                'name' => 'Director',
                'is_active' => true,
            ],
        );

        StaffPosition::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'PROJECT-MANAGER'],
            [
                'name' => 'Project Manager',
                'is_active' => true,
            ],
        );

        StaffPosition::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'ACCOUNTANT'],
            [
                'name' => 'Accountant',
                'is_active' => true,
            ],
        );

        $staff = Staff::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'staff_number' => 'POINT-001'],
            [
                'branch_id' => $branch->id,
                'staff_position_id' => $directorPosition->id,
                'name' => 'Lemi',
                'email' => 'lemi@gmail.com',
                'phone' => null,
                'status' => 'active',
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'lemi@gmail.com'],
            [
                'tenant_id' => $tenant->id,
                'staff_id' => $staff->id,
                'name' => 'Lemi',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
                'is_director' => true,
            ],
        );

        $user->syncRoles([Role::query()->where('name', 'Director')->firstOrFail()]);
        $user->branches()->syncWithoutDetaching([
            $branch->id => ['is_default' => true],
        ]);

        foreach (['USD', 'UGX'] as $currencyCode) {
            TenantCurrency::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'currency_code' => $currencyCode],
                [
                    'is_enabled' => true,
                    'is_default' => $currencyCode === $tenant->default_currency_code,
                ],
            );
        }

        BranchCurrency::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'currency_code' => 'UGX'],
            [
                'tenant_id' => $tenant->id,
                'is_enabled' => true,
                'is_default_transaction_currency' => true,
                'can_receive' => true,
                'can_pay' => true,
            ],
        );

        BranchCurrency::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'currency_code' => 'USD'],
            [
                'tenant_id' => $tenant->id,
                'is_enabled' => true,
                'is_default_transaction_currency' => false,
                'can_receive' => true,
                'can_pay' => true,
            ],
        );

        ExchangeRate::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'branch_id' => null,
                'from_currency_code' => 'USD',
                'to_currency_code' => 'UGX',
                'effective_date' => now()->toDateString(),
            ],
            [
                'rate' => '3700.0000000000',
                'source' => 'manual',
                'status' => ExchangeRate::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );
    }
}
