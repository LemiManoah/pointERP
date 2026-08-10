<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\EnsureDefaultTenant;
use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Role;
use App\Models\Site;
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
            'SITE-MANAGER' => $this->position('SITE-MANAGER', 'Site Manager'),
            'AUDITOR' => $this->position('AUDITOR', 'Auditor'),
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

        $guluProjectManager = $this->user(
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

        $guluSiteEngineer = $this->user(
            staffNumber: 'POINT-006',
            name: 'Gulu Site Engineer',
            email: 'engineer.gulu@point.test',
            branch: $branches['GUL-SITE'],
            position: $positions['SITE-ENGINEER'],
            roleName: 'Site Manager',
            branchAccess: [$branches['GUL-SITE']],
            defaultBranch: $branches['GUL-SITE'],
        );

        $guluAuditor = $this->user(
            staffNumber: 'POINT-007',
            name: 'Gulu Auditor',
            email: 'auditor.gulu@point.test',
            branch: $branches['GUL-SITE'],
            position: $positions['AUDITOR'],
            roleName: 'Auditor',
            branchAccess: [$branches['GUL-SITE']],
            defaultBranch: $branches['GUL-SITE'],
        );

        $jubaSiteManager = $this->user(
            staffNumber: 'POINT-008',
            name: 'Juba Site Manager',
            email: 'site.juba@point.test',
            branch: $branches['JUB-HQ'],
            position: $positions['SITE-MANAGER'],
            roleName: 'Site Manager',
            branchAccess: [$branches['JUB-HQ']],
            defaultBranch: $branches['JUB-HQ'],
        );

        $this->operationsDemoData(
            director: $director,
            ugandaBranch: $branches['GUL-SITE'],
            southSudanBranch: $branches['JUB-HQ'],
            ugandaProjectManager: $guluProjectManager,
            ugandaSiteEngineer: $guluSiteEngineer,
            ugandaAuditor: $guluAuditor,
            southSudanSiteManager: $jubaSiteManager,
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

    private function operationsDemoData(
        User $director,
        Branch $ugandaBranch,
        Branch $southSudanBranch,
        User $ugandaProjectManager,
        User $ugandaSiteEngineer,
        User $ugandaAuditor,
        User $southSudanSiteManager,
    ): void {
        $unra = Customer::query()->updateOrCreate(
            ['tenant_id' => $ugandaBranch->tenant_id, 'code' => 'UNRA'],
            [
                'branch_id' => $ugandaBranch->id,
                'type' => Customer::TYPE_CLIENT,
                'name' => 'Uganda National Roads Authority',
                'email' => null,
                'phone' => null,
                'tax_number' => null,
                'address' => 'Kampala, Uganda',
                'status' => 'active',
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );

        Customer::query()->updateOrCreate(
            ['tenant_id' => $ugandaBranch->tenant_id, 'code' => 'ATSGSL'],
            [
                'branch_id' => $ugandaBranch->id,
                'type' => Customer::TYPE_SUBCONTRACTOR,
                'name' => 'Abubaker Technical Services and General Supplies Limited',
                'email' => null,
                'phone' => null,
                'tax_number' => null,
                'address' => 'Kampala, Uganda',
                'status' => 'active',
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );

        $contract = Contract::query()->updateOrCreate(
            ['tenant_id' => $ugandaBranch->tenant_id, 'reference' => 'UNRA/WORKS/2021-2022/00369'],
            [
                'branch_id' => $ugandaBranch->id,
                'customer_id' => $unra->id,
                'title' => 'Civil Works for the Rehabilitation of Busunju - Kiboga - Hoima Road (145km)',
                'scope_summary' => 'Road rehabilitation works used as Phase 2A demo seed data.',
                'contract_value' => '309073180813.0000',
                'currency_code' => 'UGX',
                'starts_on' => '2023-05-10',
                'ends_on' => '2026-05-09',
                'retention_percent' => '10.0000',
                'payment_terms' => 'Interim payment certificate based on measured works.',
                'status' => 'active',
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );

        $roadProject = Project::query()->updateOrCreate(
            ['tenant_id' => $ugandaBranch->tenant_id, 'reference' => 'BKH-ROAD'],
            [
                'branch_id' => $ugandaBranch->id,
                'customer_id' => $unra->id,
                'contract_id' => $contract->id,
                'name' => 'Busunju - Kiboga - Hoima Road Rehabilitation',
                'description' => 'Demo project based on the daily costing and IPC spreadsheets.',
                'manager_id' => $ugandaProjectManager->id,
                'base_currency_code' => 'UGX',
                'budget_amount' => '309073180813.0000',
                'starts_on' => '2023-05-10',
                'ends_on' => '2026-05-09',
                'reporting_deadline' => '18:00',
                'status' => 'active',
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );

        $busunjuSite = $this->site($roadProject, $ugandaSiteEngineer, 'BUSUNJU', 'Busunju Section', 'Km 0+000 - Km 74+000');
        $kibogaSite = $this->site($roadProject, $ugandaProjectManager, 'KIBOGA-HOIMA', 'Kiboga-Hoima Section', 'Km 74+000 - Km 145+000');

        $roadProject->users()->syncWithoutDetaching([
            $ugandaProjectManager->id => ['role' => 'Project Manager', 'can_manage' => true],
            $ugandaAuditor->id => ['role' => 'Auditor', 'can_manage' => false],
        ]);

        $busunjuSite->users()->syncWithoutDetaching([
            $ugandaSiteEngineer->id => ['role' => 'Site Engineer', 'can_submit_dsr' => true, 'can_review_dsr' => false],
            $ugandaProjectManager->id => ['role' => 'Project Manager', 'can_submit_dsr' => false, 'can_review_dsr' => true],
        ]);
        $kibogaSite->users()->syncWithoutDetaching([
            $ugandaProjectManager->id => ['role' => 'Project Manager', 'can_submit_dsr' => true, 'can_review_dsr' => true],
        ]);

        foreach ($this->roadActivities($roadProject, $busunjuSite, $kibogaSite) as $activity) {
            ProjectActivity::query()->updateOrCreate(
                [
                    'tenant_id' => $roadProject->tenant_id,
                    'project_id' => $roadProject->id,
                    'boq_item_number' => $activity['boq_item_number'],
                ],
                [
                    ...$activity,
                    'branch_id' => $roadProject->branch_id,
                    'created_by' => $director->id,
                    'updated_by' => $director->id,
                ],
            );
        }

        $southSudanCustomer = Customer::query()->updateOrCreate(
            ['tenant_id' => $southSudanBranch->tenant_id, 'code' => 'SSRA'],
            [
                'branch_id' => $southSudanBranch->id,
                'type' => Customer::TYPE_CLIENT,
                'name' => 'South Sudan Roads Authority',
                'status' => 'active',
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );

        $southSudanProject = Project::query()->updateOrCreate(
            ['tenant_id' => $southSudanBranch->tenant_id, 'reference' => 'JUBA-ACCESS'],
            [
                'branch_id' => $southSudanBranch->id,
                'customer_id' => $southSudanCustomer->id,
                'contract_id' => null,
                'name' => 'Juba Access Road Works',
                'description' => 'Demo South Sudan project for branch isolation testing.',
                'manager_id' => $southSudanSiteManager->id,
                'base_currency_code' => 'USD',
                'budget_amount' => '2500000.0000',
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addYear()->toDateString(),
                'reporting_deadline' => '18:00',
                'status' => 'active',
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );
        $southSudanProject->users()->syncWithoutDetaching([
            $southSudanSiteManager->id => ['role' => 'Site Manager', 'can_manage' => true],
        ]);
        $this->site($southSudanProject, $southSudanSiteManager, 'JUBA-MAIN', 'Juba Main Site', 'Juba access works');
    }

    private function site(Project $project, User $manager, string $reference, string $name, string $location): Site
    {
        return Site::query()->updateOrCreate(
            ['project_id' => $project->id, 'reference' => $reference],
            [
                'tenant_id' => $project->tenant_id,
                'branch_id' => $project->branch_id,
                'name' => $name,
                'location_name' => $location,
                'manager_id' => $manager->id,
                'reporting_deadline' => $project->reporting_deadline,
                'status' => 'active',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roadActivities(Project $project, Site $busunjuSite, Site $kibogaSite): array
    {
        return [
            [
                'project_id' => $project->id,
                'site_id' => $busunjuSite->id,
                'code' => 'MAINT-ROAD',
                'boq_item_number' => '12.03(a)',
                'name' => 'Maintenance of existing road',
                'unit' => 'month',
                'planned_quantity' => '36.0000',
                'approved_quantity' => '0.0000',
                'rate_amount' => '4000000.0000',
                'currency_code' => 'UGX',
                'status' => 'active',
                'sort_order' => 10,
            ],
            [
                'project_id' => $project->id,
                'site_id' => $busunjuSite->id,
                'code' => 'TOPSOIL',
                'boq_item_number' => '31.01(b)(i)',
                'name' => 'Removal of top soil',
                'unit' => 'm3',
                'planned_quantity' => null,
                'approved_quantity' => '0.0000',
                'rate_amount' => null,
                'currency_code' => 'UGX',
                'status' => 'active',
                'sort_order' => 20,
            ],
            [
                'project_id' => $project->id,
                'site_id' => $kibogaSite->id,
                'code' => 'EXC-SPOIL',
                'boq_item_number' => '36.01(a)',
                'name' => 'Excavation to spoil',
                'unit' => 'm3',
                'planned_quantity' => null,
                'approved_quantity' => '0.0000',
                'rate_amount' => null,
                'currency_code' => 'UGX',
                'status' => 'active',
                'sort_order' => 30,
            ],
            [
                'project_id' => $project->id,
                'site_id' => $kibogaSite->id,
                'code' => 'SUBBASE',
                'boq_item_number' => '37.02(c)',
                'name' => 'Natural material for subbase',
                'unit' => 'm3',
                'planned_quantity' => null,
                'approved_quantity' => '0.0000',
                'rate_amount' => null,
                'currency_code' => 'UGX',
                'status' => 'active',
                'sort_order' => 40,
            ],
            [
                'project_id' => $project->id,
                'site_id' => null,
                'code' => 'PETROL',
                'boq_item_number' => '82.02',
                'name' => 'Petrol',
                'unit' => 'litre',
                'planned_quantity' => null,
                'approved_quantity' => '0.0000',
                'rate_amount' => null,
                'currency_code' => 'UGX',
                'status' => 'active',
                'sort_order' => 50,
            ],
            [
                'project_id' => $project->id,
                'site_id' => null,
                'code' => 'EXCAVATOR',
                'boq_item_number' => '83.05',
                'name' => 'Excavator',
                'unit' => 'hour',
                'planned_quantity' => null,
                'approved_quantity' => '0.0000',
                'rate_amount' => null,
                'currency_code' => 'UGX',
                'status' => 'active',
                'sort_order' => 60,
            ],
        ];
    }
}
