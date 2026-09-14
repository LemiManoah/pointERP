<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

final class IronPointProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        foreach ([
            ['USD', 'United States Dollar', '$', 2],
            ['UGX', 'Ugandan Shilling', 'UGX', 0],
            ['SSP', 'South Sudanese Pound', 'SSP', 2],
            ['CDF', 'Congolese Franc', 'CDF', 2],
        ] as [$code, $name, $symbol, $decimalPlaces]) {
            Currency::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'symbol' => $symbol,
                'decimal_places' => $decimalPlaces,
                'is_active' => true,
            ]);
        }

        foreach ([
            ['UG', 'Uganda', 'UGA', 'UGX'],
            ['SS', 'South Sudan', 'SSD', 'SSP'],
            ['CD', 'Democratic Republic of the Congo', 'COD', 'CDF'],
        ] as [$code, $name, $iso3, $currencyCode]) {
            Country::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'iso3_code' => $iso3,
                'default_currency_code' => $currencyCode,
                'is_active' => true,
            ]);
        }

        $tenant = Tenant::query()->withTrashed()->firstOrNew(['code' => 'IRONPOINT']);
        $tenant->fill([
            'name' => 'IronPoint Capital Investments Limited',
            'default_currency_code' => 'UGX',
            'is_multibranch' => true,
            'multi_currency_enabled' => true,
            'multi_store_enabled' => true,
            'timezone' => 'Africa/Kampala',
            'status' => 'active',
        ]);
        $tenant->deleted_at = null;
        $tenant->save();

        resolve(TenantContext::class)->set($tenant);

        foreach (['UGX', 'USD', 'SSP', 'CDF'] as $index => $currencyCode) {
            $tenantCurrency = TenantCurrency::query()->withTrashed()->firstOrNew([
                'tenant_id' => $tenant->id,
                'currency_code' => $currencyCode,
            ]);
            $tenantCurrency->fill(['is_enabled' => true, 'is_default' => $index === 0]);
            $tenantCurrency->deleted_at = null;
            $tenantCurrency->save();
        }

        $branches = [
            $this->upsertBranch($tenant, 'IP-KLA-HQ', 'Kampala Head Office', 'UG', 'UGX'),
            $this->upsertBranch($tenant, 'IP-JUBA', 'Juba Office', 'SS', 'SSP'),
            $this->upsertBranch($tenant, 'IP-DRC', 'DRC Office', 'CD', 'CDF'),
        ];

        foreach ($branches as $branch) {
            foreach (['UGX', 'USD', 'SSP', 'CDF'] as $currencyCode) {
                $this->upsertBranchCurrency(
                    $tenant->id,
                    $branch->id,
                    $currencyCode,
                    $currencyCode === $branch->default_currency_code,
                );
            }
        }

        $administrator = Role::query()->where('name', 'Administrator')->where('guard_name', 'web')->firstOrFail();

        foreach ([
            'lemi.manoah@gmail.com' => 'Lemi Manoah',
            'jacksonsimeon17@gmail.com' => 'Jackson Simeon',
        ] as $email => $name) {
            $user = User::query()->where('email', $email)->first();

            if ($user instanceof User && $user->tenant_id !== $tenant->id) {
                throw new RuntimeException(sprintf('The admin email %s is already assigned to another tenant.', $email));
            }

            if (! $user instanceof User) {
                $user = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'email' => $email,
                    'password' => 'password',
                    'email_verified_at' => Carbon::now(),
                    'is_active' => true,
                    'is_director' => true,
                    'is_support' => $email === 'lemi.manoah@gmail.com',
                ]);
            } else {
                $user->update([
                    'name' => $name,
                    'is_active' => true,
                    'is_director' => true,
                    'is_support' => $email === 'lemi.manoah@gmail.com',
                ]);
            }

            $user->branches()->sync(collect($branches)->mapWithKeys(
                fn (Branch $branch): array => [$branch->id => ['is_default' => $branch->code === 'IP-KLA-HQ']],
            )->all());
            $user->syncRoles([$administrator]);
        }
    }

    private function upsertBranch(Tenant $tenant, string $code, string $name, string $countryCode, string $currencyCode): Branch
    {
        $branch = Branch::query()->withTrashed()->firstOrNew([
            'tenant_id' => $tenant->id,
            'code' => $code,
        ]);
        $branch->fill([
            'name' => $name,
            'country_code' => $countryCode,
            'default_currency_code' => $currencyCode,
            'status' => 'active',
        ]);
        $branch->deleted_at = null;
        $branch->save();

        return $branch;
    }

    private function upsertBranchCurrency(string $tenantId, string $branchId, string $currencyCode, bool $isDefault): void
    {
        $branchCurrency = BranchCurrency::query()->withTrashed()->firstOrNew([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'currency_code' => $currencyCode,
        ]);
        $branchCurrency->fill([
            'is_enabled' => true,
            'is_default_transaction_currency' => $isDefault,
            'can_receive' => true,
            'can_pay' => true,
        ]);
        $branchCurrency->deleted_at = null;
        $branchCurrency->save();
    }
}
