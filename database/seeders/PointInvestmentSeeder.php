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

        $branches = [
            'KLA-HQ' => $this->branch('KLA-HQ', 'Kampala Head Office', 'UG', 'UGX'),
            'GUL-SITE' => $this->branch('GUL-SITE', 'Gulu Project Office', 'UG', 'UGX'),
            'JUB-HQ' => $this->branch('JUB-HQ', 'Juba Office', 'SS', 'USD'),
            'KIN-MOB' => $this->branch('KIN-MOB', 'Kinshasa Mobilization Office', 'CD', 'CDF', 'inactive'),
        ];

        $positions = [
            'DIRECTOR' => $this->position('DIRECTOR', 'Director'),
            'ADMINISTRATOR' => $this->position('ADMINISTRATOR', 'Administrator'),
            'PROJECT-MANAGER' => $this->position('PROJECT-MANAGER', 'Project Manager'),
            'ACCOUNTANT' => $this->position('ACCOUNTANT', 'Accountant'),
            'STORE-KEEPER' => $this->position('STORE-KEEPER', 'Store Keeper'),
            'SITE-ENGINEER' => $this->position('SITE-ENGINEER', 'Site Engineer'),
        ];

        foreach (['USD', 'UGX', 'SSP', 'CDF'] as $currencyCode) {
            TenantCurrency::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'currency_code' => $currencyCode],
                [
                    'is_enabled' => true,
                    'is_default' => $currencyCode === $tenant->default_currency_code,
                ],
            );
        }

        $this->branchCurrencies($branches['KLA-HQ'], ['UGX' => true, 'USD' => false]);
        $this->branchCurrencies($branches['GUL-SITE'], ['UGX' => true, 'USD' => false]);
        $this->branchCurrencies($branches['JUB-HQ'], ['USD' => true, 'SSP' => false]);
        $this->branchCurrencies($branches['KIN-MOB'], ['CDF' => true, 'USD' => false]);

        $director = $this->user(
            staffNumber: 'POINT-001',
            name: 'Lemi',
            email: 'lemi@gmail.com',
            branch: $branches['KLA-HQ'],
            position: $positions['DIRECTOR'],
            roleName: 'Director',
            branchAccess: [$branches['KLA-HQ'], $branches['GUL-SITE'], $branches['JUB-HQ']],
            defaultBranch: $branches['KLA-HQ'],
            isDirector: true,
        );

        $this->user(
            staffNumber: 'POINT-002',
            name: 'Kampala Admin',
            email: 'admin.kla@point.test',
            branch: $branches['KLA-HQ'],
            position: $positions['ADMINISTRATOR'],
            roleName: 'Administrator',
            branchAccess: [$branches['KLA-HQ']],
            defaultBranch: $branches['KLA-HQ'],
        );

        $this->user(
            staffNumber: 'POINT-003',
            name: 'Gulu Project Manager',
            email: 'pm.gulu@point.test',
            branch: $branches['GUL-SITE'],
            position: $positions['PROJECT-MANAGER'],
            roleName: 'Project Manager',
            branchAccess: [$branches['GUL-SITE']],
            defaultBranch: $branches['GUL-SITE'],
        );

        $this->user(
            staffNumber: 'POINT-004',
            name: 'Juba Accountant',
            email: 'accountant.juba@point.test',
            branch: $branches['JUB-HQ'],
            position: $positions['ACCOUNTANT'],
            roleName: 'Accountant',
            branchAccess: [$branches['JUB-HQ'], $branches['KLA-HQ']],
            defaultBranch: $branches['JUB-HQ'],
        );

        $this->user(
            staffNumber: 'POINT-005',
            name: 'Kampala Store Keeper',
            email: 'store.kla@point.test',
            branch: $branches['KLA-HQ'],
            position: $positions['STORE-KEEPER'],
            roleName: 'Store Keeper',
            branchAccess: [$branches['KLA-HQ']],
            defaultBranch: $branches['KLA-HQ'],
        );

        $this->staff(
            staffNumber: 'POINT-006',
            name: 'Gulu Site Engineer',
            email: 'engineer.gulu@point.test',
            branch: $branches['GUL-SITE'],
            position: $positions['SITE-ENGINEER'],
        );

        $this->exchangeRate($director, null, 'USD', 'UGX', '3600.0000000000', now()->subMonth()->toDateString(), ExchangeRate::STATUS_APPROVED);
        $this->exchangeRate($director, null, 'USD', 'UGX', '3700.0000000000', now()->toDateString(), ExchangeRate::STATUS_DRAFT);
        $this->exchangeRate($director, $branches['JUB-HQ'], 'USD', 'SSP', '1500.0000000000', now()->toDateString(), ExchangeRate::STATUS_APPROVED);
        $this->exchangeRate($director, $branches['KLA-HQ'], 'UGX', 'USD', '0.0002702703', now()->toDateString(), ExchangeRate::STATUS_DRAFT);
    }

    private function branch(string $code, string $name, string $countryCode, string $currencyCode, string $status = 'active'): Branch
    {
        return Branch::query()->updateOrCreate(
            ['tenant_id' => resolve(TenantContext::class)->id(), 'code' => $code],
            [
                'name' => $name,
                'country_code' => $countryCode,
                'default_currency_code' => $currencyCode,
                'status' => $status,
            ],
        );
    }

    private function position(string $code, string $name): StaffPosition
    {
        return StaffPosition::query()->updateOrCreate(
            ['tenant_id' => resolve(TenantContext::class)->id(), 'code' => $code],
            [
                'name' => $name,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, bool>  $settings
     */
    private function branchCurrencies(Branch $branch, array $settings): void
    {
        foreach ($settings as $currencyCode => $isDefault) {
            BranchCurrency::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'currency_code' => $currencyCode],
                [
                    'tenant_id' => $branch->tenant_id,
                    'is_enabled' => true,
                    'is_default_transaction_currency' => $isDefault,
                    'can_receive' => true,
                    'can_pay' => true,
                ],
            );
        }
    }

    private function staff(string $staffNumber, string $name, string $email, Branch $branch, StaffPosition $position): Staff
    {
        return Staff::query()->updateOrCreate(
            ['tenant_id' => $branch->tenant_id, 'staff_number' => $staffNumber],
            [
                'branch_id' => $branch->id,
                'staff_position_id' => $position->id,
                'name' => $name,
                'email' => $email,
                'phone' => null,
                'status' => 'active',
            ],
        );
    }

    /**
     * @param  list<Branch>  $branchAccess
     */
    private function user(
        string $staffNumber,
        string $name,
        string $email,
        Branch $branch,
        StaffPosition $position,
        string $roleName,
        array $branchAccess,
        Branch $defaultBranch,
        bool $isDirector = false,
    ): User {
        $staff = $this->staff($staffNumber, $name, $email, $branch, $position);

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'tenant_id' => $branch->tenant_id,
                'staff_id' => $staff->id,
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
                'is_director' => $isDirector,
            ],
        );

        $user->syncRoles([Role::query()->where('name', $roleName)->firstOrFail()]);
        $user->branches()->sync(
            collect($branchAccess)
                ->mapWithKeys(fn (Branch $allowedBranch): array => [
                    $allowedBranch->id => ['is_default' => $allowedBranch->id === $defaultBranch->id],
                ])
                ->all(),
        );

        return $user;
    }

    private function exchangeRate(User $actor, ?Branch $branch, string $fromCurrency, string $toCurrency, string $rate, string $effectiveDate, string $status): void
    {
        ExchangeRate::query()->updateOrCreate(
            [
                'tenant_id' => $actor->tenant_id,
                'branch_id' => $branch?->id,
                'from_currency_code' => $fromCurrency,
                'to_currency_code' => $toCurrency,
                'effective_date' => $effectiveDate,
            ],
            [
                'rate' => $rate,
                'source' => 'manual',
                'status' => $status,
                'approved_by' => $status === ExchangeRate::STATUS_APPROVED ? $actor->id : null,
                'approved_at' => $status === ExchangeRate::STATUS_APPROVED ? now() : null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }
}
