<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\EnsureDefaultTenant;
use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\DailySiteReportCostLine;
use App\Models\DailySiteReportDelayLine;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\DailySiteReportLabourLine;
use App\Models\DailySiteReportMaterialLine;
use App\Models\DailySiteReportReview;
use App\Models\DailySiteReportWorkLine;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\ExchangeRate;
use App\Models\ExpectedDailySiteReport;
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
use Illuminate\Support\Facades\Storage;

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

        $this->documentTypes($director);

        $this->user(
            staffNumber: 'POINT-SUPPORT-001',
            name: 'Point Support Admin',
            email: 'support@pointmanager.test',
            branch: $branches['KLA-HQ'],
            position: $positions['ADMINISTRATOR'],
            roleName: 'Administrator',
            branchAccess: [$branches['KLA-HQ'], $branches['GUL-SITE'], $branches['JUB-HQ']],
            defaultBranch: $branches['KLA-HQ'],
            isSupport: true,
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
        bool $isSupport = false,
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
                'is_support' => $isSupport,
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

        $this->dailySiteReports($director, $ugandaProjectManager, $ugandaSiteEngineer, $roadProject, $busunjuSite, $kibogaSite);
        $this->seedProjectDocuments($director, $roadProject, $contract, $busunjuSite, $kibogaSite);

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
        $jubaSite = $this->site($southSudanProject, $southSudanSiteManager, 'JUBA-MAIN', 'Juba Main Site', 'Juba access works');
        $this->dailySiteReports($director, $southSudanSiteManager, $southSudanSiteManager, $southSudanProject, $jubaSite, $jubaSite, 'USD');
        $this->seedDocument(
            actor: $director,
            typeCode: 'METHOD_STATEMENT',
            branch: $southSudanBranch,
            title: 'Juba Access Road Method Statement',
            reference: 'JUBA-MS-001',
            content: 'Demo method statement for South Sudan branch isolation.',
            links: [[$southSudanProject::class, $southSudanProject->id]],
        );
    }

    private function documentTypes(User $actor): void
    {
        $types = [
            ['CONTRACT', 'Contract', false, true],
            ['CONTRACT_ADDENDUM', 'Contract addendum', false, true],
            ['DRAWING', 'Drawing', false, false],
            ['REVISED_DRAWING', 'Revised drawing', false, false],
            ['METHOD_STATEMENT', 'Method statement', false, false],
            ['PERMIT', 'Permit', true, false],
            ['TEST_RESULT', 'Test result', false, false],
            ['INSPECTION_RECORD', 'Inspection record', false, false],
            ['SITE_INSTRUCTION', 'Site instruction', false, false],
            ['RFI', 'Request for information', false, false],
            ['RFA', 'Request for approval', false, false],
            ['DSR_EVIDENCE', 'DSR evidence', false, false],
            ['PHOTO', 'Photo', false, false],
            ['SKETCH', 'Sketch', false, false],
            ['HSE_RECORD', 'HSE record', false, false],
            ['ENVIRONMENT_RECORD', 'Environment record', false, false],
            ['SOCIAL_RECORD', 'Social record', false, false],
            ['IPC_SUPPORT', 'IPC support', false, true],
            ['CORRESPONDENCE', 'Correspondence', false, false],
        ];

        foreach ($types as [$code, $name, $requiresExpiry, $isConfidential]) {
            DocumentType::query()->updateOrCreate(
                ['tenant_id' => null, 'code' => $code],
                [
                    'name' => $name,
                    'description' => $name.' records for project document control.',
                    'requires_expiry_date' => $requiresExpiry,
                    'is_confidential' => $isConfidential,
                    'is_system' => true,
                    'is_active' => true,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ],
            );
        }
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

    private function dailySiteReports(
        User $director,
        User $reviewer,
        User $submitter,
        Project $project,
        Site $primarySite,
        Site $secondarySite,
        string $currencyCode = 'UGX',
    ): void {
        $submittedReport = $this->dailySiteReport(
            project: $project,
            site: $primarySite,
            actor: $submitter,
            reference: 'DSR-'.$primarySite->reference.'-20241207',
            reportDate: '2024-12-07',
            status: DailySiteReport::STATUS_SUBMITTED,
            currencyCode: $currencyCode,
            submittedBy: $submitter,
        );
        $this->seedReportLines($submittedReport, $currencyCode);
        $this->reviewEvent($submittedReport, $submitter, DailySiteReportReview::ACTION_SUBMITTED);

        $approvedReport = $this->dailySiteReport(
            project: $project,
            site: $primarySite,
            actor: $submitter,
            reference: 'DSR-'.$primarySite->reference.'-20241206',
            reportDate: '2024-12-06',
            status: DailySiteReport::STATUS_APPROVED,
            currencyCode: $currencyCode,
            submittedBy: $submitter,
            approvedBy: $reviewer,
        );
        $this->seedReportLines($approvedReport, $currencyCode, 'Km 12+400', 'Km 13+200');
        $this->reviewEvent($approvedReport, $submitter, DailySiteReportReview::ACTION_SUBMITTED);
        $this->reviewEvent($approvedReport, $reviewer, DailySiteReportReview::ACTION_APPROVED);
        $this->correctionRequest($approvedReport, $director);

        $returnedReport = $this->dailySiteReport(
            project: $project,
            site: $secondarySite,
            actor: $submitter,
            reference: 'DSR-'.$secondarySite->reference.'-20241208',
            reportDate: '2024-12-08',
            status: DailySiteReport::STATUS_RETURNED,
            currencyCode: $currencyCode,
            submittedBy: $submitter,
            returnedBy: $reviewer,
            returnReason: 'Clarify excavator hours and attach the measurement sketch before resubmission.',
        );
        $this->seedReportLines($returnedReport, $currencyCode, 'Km 88+000', 'Km 89+500');
        $this->reviewEvent($returnedReport, $submitter, DailySiteReportReview::ACTION_SUBMITTED);
        $this->reviewEvent($returnedReport, $reviewer, DailySiteReportReview::ACTION_RETURNED, 'Clarify excavator hours and attach the measurement sketch before resubmission.');

        $draftReport = $this->dailySiteReport(
            project: $project,
            site: $secondarySite,
            actor: $submitter,
            reference: 'DSR-'.$secondarySite->reference.'-'.now()->toDateString(),
            reportDate: now()->toDateString(),
            status: DailySiteReport::STATUS_DRAFT,
            currencyCode: $currencyCode,
        );

        $missingReport = $this->dailySiteReport(
            project: $project,
            site: $primarySite,
            actor: $submitter,
            reference: 'DSR-'.$primarySite->reference.'-'.now()->subDay()->format('Ymd'),
            reportDate: now()->subDay()->toDateString(),
            status: DailySiteReport::STATUS_MISSING,
            currencyCode: $currencyCode,
        );

        foreach ([$submittedReport, $approvedReport, $returnedReport, $draftReport, $missingReport] as $report) {
            ExpectedDailySiteReport::query()->updateOrCreate(
                [
                    'tenant_id' => $project->tenant_id,
                    'site_id' => $report->site_id,
                    'report_date' => $report->report_date->toDateString(),
                ],
                [
                    'branch_id' => $project->branch_id,
                    'project_id' => $project->id,
                    'deadline_at' => $report->report_date->setTime(18, 0),
                    'status' => match ($report->status) {
                        DailySiteReport::STATUS_SUBMITTED, DailySiteReport::STATUS_APPROVED => ExpectedDailySiteReport::STATUS_SUBMITTED,
                        DailySiteReport::STATUS_MISSING => ExpectedDailySiteReport::STATUS_MISSING,
                        default => ExpectedDailySiteReport::STATUS_EXPECTED,
                    },
                    'daily_site_report_id' => $report->id,
                    'submitted_at' => $report->submitted_at,
                    'notified_at' => $report->status === DailySiteReport::STATUS_MISSING ? now() : null,
                    'escalated_at' => null,
                ],
            );
        }

        ExpectedDailySiteReport::query()->updateOrCreate(
            [
                'tenant_id' => $project->tenant_id,
                'site_id' => $secondarySite->id,
                'report_date' => now()->addDay()->toDateString(),
            ],
            [
                'branch_id' => $project->branch_id,
                'project_id' => $project->id,
                'deadline_at' => now()->addDay()->setTime(18, 0),
                'status' => ExpectedDailySiteReport::STATUS_EXPECTED,
                'daily_site_report_id' => null,
                'submitted_at' => null,
                'notified_at' => null,
                'escalated_at' => null,
            ],
        );
    }

    private function reviewEvent(DailySiteReport $report, User $actor, string $action, ?string $remarks = null): void
    {
        DailySiteReportReview::query()->updateOrCreate(
            [
                'daily_site_report_id' => $report->id,
                'reviewed_by' => $actor->id,
                'action' => $action,
            ],
            [
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'remarks' => $remarks,
            ],
        );
    }

    private function correctionRequest(DailySiteReport $report, User $actor): void
    {
        DailySiteReportCorrection::query()->updateOrCreate(
            [
                'daily_site_report_id' => $report->id,
                'requested_by' => $actor->id,
                'status' => DailySiteReportCorrection::STATUS_SUBMITTED,
            ],
            [
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'reason' => 'Correct completion percentage after QS verification.',
                'old_values' => ['completion_percent' => $report->completion_percent],
                'new_values' => ['completion_percent' => '39.2500'],
            ],
        );
    }

    private function dailySiteReport(
        Project $project,
        Site $site,
        User $actor,
        string $reference,
        string $reportDate,
        string $status,
        string $currencyCode,
        ?User $submittedBy = null,
        ?User $approvedBy = null,
        ?User $returnedBy = null,
        ?string $returnReason = null,
    ): DailySiteReport {
        return DailySiteReport::query()->updateOrCreate(
            ['tenant_id' => $project->tenant_id, 'reference' => $reference],
            [
                'branch_id' => $project->branch_id,
                'project_id' => $project->id,
                'site_id' => $site->id,
                'report_date' => $reportDate,
                'weather' => 'Partly cloudy',
                'site_conditions' => 'Traffic managed with usable diversion.',
                'work_summary' => 'Road maintenance, earthworks and material handling continued.',
                'delay_summary' => 'Intermittent rain slowed haulage in the afternoon.',
                'visitor_summary' => 'Client representative visited the chainage section.',
                'hse_notes' => 'Toolbox talk completed before works commenced.',
                'environment_notes' => 'Dust suppression maintained through water bowser trips.',
                'social_notes' => 'No community grievances recorded.',
                'completion_percent' => '38.5000',
                'output_value' => '0.0000',
                'input_cost' => '0.0000',
                'profit_loss' => '0.0000',
                'status' => $status,
                'submitted_by' => $submittedBy?->id,
                'submitted_at' => $submittedBy instanceof User ? now()->subHours(4) : null,
                'reviewed_by' => $approvedBy?->id,
                'reviewed_at' => $approvedBy instanceof User ? now()->subHours(2) : null,
                'approved_by' => $approvedBy?->id,
                'approved_at' => $approvedBy instanceof User ? now()->subHour() : null,
                'returned_by' => $returnedBy?->id,
                'returned_at' => $returnedBy instanceof User ? now()->subHours(2) : null,
                'return_reason' => $returnReason,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }

    private function seedReportLines(DailySiteReport $report, string $currencyCode, string $chainageFrom = 'Km 10+000', string $chainageTo = 'Km 11+500'): void
    {
        foreach ([DailySiteReportWorkLine::class, DailySiteReportLabourLine::class, DailySiteReportEquipmentLine::class, DailySiteReportMaterialLine::class, DailySiteReportCostLine::class, DailySiteReportDelayLine::class] as $modelClass) {
            $modelClass::query()->where('daily_site_report_id', $report->id)->delete();
        }

        DailySiteReportWorkLine::query()->create([
            'tenant_id' => $report->tenant_id,
            'branch_id' => $report->branch_id,
            'daily_site_report_id' => $report->id,
            'site_id' => $report->site_id,
            'boq_item_number' => '31.01(b)(i)',
            'description' => 'Removal of top soil',
            'chainage_from' => $chainageFrom,
            'chainage_to' => $chainageTo,
            'side' => 'LHS',
            'quantity' => '420.0000',
            'unit' => 'm3',
            'rate_amount' => $currencyCode === 'UGX' ? '8500.0000' : '2.3000',
            'amount' => $currencyCode === 'UGX' ? '3570000.0000' : '966.0000',
            'currency_code' => $currencyCode,
            'notes' => 'Quantity subject to PM approval.',
            'sort_order' => 1,
        ]);

        DailySiteReportLabourLine::query()->create([
            'tenant_id' => $report->tenant_id,
            'branch_id' => $report->branch_id,
            'daily_site_report_id' => $report->id,
            'trade_or_role' => 'General labour',
            'subcontractor_name' => 'Abubaker Technical Services and General Supplies Limited',
            'headcount' => 18,
            'hours' => '8.0000',
            'rate_amount' => $currencyCode === 'UGX' ? '25000.0000' : '7.0000',
            'amount' => $currencyCode === 'UGX' ? '3600000.0000' : '1008.0000',
            'currency_code' => $currencyCode,
            'sort_order' => 1,
        ]);

        DailySiteReportEquipmentLine::query()->create([
            'tenant_id' => $report->tenant_id,
            'branch_id' => $report->branch_id,
            'daily_site_report_id' => $report->id,
            'equipment_name' => 'Excavator',
            'equipment_identifier' => 'EXC-03',
            'status' => 'working',
            'working_hours' => '7.5000',
            'idle_hours' => '0.5000',
            'fuel_type' => 'diesel',
            'fuel_quantity' => '95.0000',
            'rate_amount' => $currencyCode === 'UGX' ? '180000.0000' : '48.0000',
            'amount' => $currencyCode === 'UGX' ? '1350000.0000' : '360.0000',
            'currency_code' => $currencyCode,
            'sort_order' => 1,
        ]);

        DailySiteReportMaterialLine::query()->create([
            'tenant_id' => $report->tenant_id,
            'branch_id' => $report->branch_id,
            'daily_site_report_id' => $report->id,
            'material_name' => 'Petrol',
            'material_type' => 'fuel',
            'quantity' => '120.0000',
            'unit' => 'litre',
            'rate_amount' => $currencyCode === 'UGX' ? '5600.0000' : '1.5000',
            'amount' => $currencyCode === 'UGX' ? '672000.0000' : '180.0000',
            'currency_code' => $currencyCode,
            'delivery_reference' => 'FUEL-DSR-DEMO',
            'sort_order' => 1,
        ]);

        DailySiteReportCostLine::query()->create([
            'tenant_id' => $report->tenant_id,
            'branch_id' => $report->branch_id,
            'daily_site_report_id' => $report->id,
            'category' => 'allowances',
            'description' => 'Field team allowances',
            'quantity' => '1.0000',
            'unit' => 'day',
            'rate_amount' => $currencyCode === 'UGX' ? '450000.0000' : '120.0000',
            'amount' => $currencyCode === 'UGX' ? '450000.0000' : '120.0000',
            'currency_code' => $currencyCode,
            'sort_order' => 1,
        ]);

        DailySiteReportDelayLine::query()->create([
            'tenant_id' => $report->tenant_id,
            'branch_id' => $report->branch_id,
            'daily_site_report_id' => $report->id,
            'delay_type' => 'weather',
            'description' => 'Afternoon rain slowed haulage.',
            'hours_lost' => '1.5000',
            'action_taken' => 'Rescheduled haulage balance to following day.',
            'sort_order' => 1,
        ]);

        $report->forceFill([
            'output_value' => $currencyCode === 'UGX' ? '3570000.0000' : '966.0000',
            'input_cost' => $currencyCode === 'UGX' ? '6072000.0000' : '1668.0000',
            'profit_loss' => $currencyCode === 'UGX' ? '-2502000.0000' : '-702.0000',
        ])->save();
    }

    private function seedProjectDocuments(User $director, Project $project, Contract $contract, Site $busunjuSite, Site $kibogaSite): void
    {
        $this->seedDocument(
            actor: $director,
            typeCode: 'CONTRACT',
            branch: $project->branch,
            title: 'Signed UNRA road rehabilitation contract',
            reference: 'UNRA/WORKS/2021-2022/00369',
            content: 'Demo signed contract evidence for the Busunju - Kiboga - Hoima road project.',
            links: [[$contract::class, $contract->id], [$project::class, $project->id]],
            confidentiality: Document::CONFIDENTIALITY_COMMERCIAL,
            documentNumber: 'UNRA/WORKS/2021-2022/00369',
            revision: 'Signed',
            discipline: 'Commercial',
            issuer: 'UNRA',
        );

        $this->seedDocument(
            actor: $director,
            typeCode: 'DRAWING',
            branch: $project->branch,
            title: 'BKH Road general alignment drawing',
            reference: 'BKH-ROAD-GA-001',
            content: 'Revision A general alignment drawing placeholder.',
            links: [[$project::class, $project->id], [$busunjuSite::class, $busunjuSite->id]],
            documentNumber: 'BKH-ROAD-GA-001',
            revision: 'A',
            discipline: 'Roadworks',
            issuer: 'Point Design Office',
            status: Document::STATUS_SUPERSEDED,
        );

        $this->seedDocument(
            actor: $director,
            typeCode: 'REVISED_DRAWING',
            branch: $project->branch,
            title: 'BKH Road general alignment drawing revision B',
            reference: 'BKH-ROAD-GA-001-REV-B',
            content: 'Revision B general alignment drawing placeholder.',
            links: [[$project::class, $project->id], [$busunjuSite::class, $busunjuSite->id]],
            documentNumber: 'BKH-ROAD-GA-001',
            revision: 'B',
            discipline: 'Roadworks',
            issuer: 'Point Design Office',
        );

        $this->seedDocument(
            actor: $director,
            typeCode: 'PERMIT',
            branch: $project->branch,
            title: 'Traffic management permit',
            reference: 'BKH-TMP-2024',
            content: 'Traffic management permit expiring soon for dashboard testing.',
            links: [[$project::class, $project->id], [$busunjuSite::class, $busunjuSite->id]],
            expiresOn: now()->addDays(21)->toDateString(),
            documentNumber: 'BKH-TMP-2024',
            revision: 'Approved',
            discipline: 'Traffic',
            issuer: 'Traffic Police',
        );

        $this->seedDocument(
            actor: $director,
            typeCode: 'METHOD_STATEMENT',
            branch: $project->branch,
            title: 'Topsoil removal method statement',
            reference: 'BKH-MS-TOPSOIL',
            content: 'Method statement for topsoil removal, haulage and environmental controls.',
            links: [[$project::class, $project->id], [$busunjuSite::class, $busunjuSite->id]],
            documentNumber: 'BKH-MS-TOPSOIL',
            revision: '0',
            discipline: 'Earthworks',
            issuer: 'Point Engineering',
        );

        $report = DailySiteReport::query()->where('reference', 'DSR-BUSUNJU-20241207')->first();

        if ($report instanceof DailySiteReport) {
            $this->seedDocument(
                actor: $director,
                typeCode: 'DSR_EVIDENCE',
                branch: $project->branch,
                title: 'Photo evidence for topsoil removal',
                reference: 'DSR-BUSUNJU-20241207-PHOTO-001',
                content: 'Photo placeholder: topsoil removal at Km 10+000 to Km 11+500 LHS.',
                links: [[$report::class, $report->id], [$busunjuSite::class, $busunjuSite->id]],
                documentNumber: 'DSR-BUSUNJU-20241207-PHOTO-001',
                discipline: 'Field Evidence',
                issuer: 'Site Team',
            );
            $this->seedDocument(
                actor: $director,
                typeCode: 'SKETCH',
                branch: $project->branch,
                title: 'Measurement sketch for DSR-BUSUNJU-20241207',
                reference: 'DSR-BUSUNJU-20241207-SK-001',
                content: 'Sketch placeholder supporting measured quantity and chainage.',
                links: [[$report::class, $report->id], [$busunjuSite::class, $busunjuSite->id]],
                documentNumber: 'DSR-BUSUNJU-20241207-SK-001',
                revision: '0',
                discipline: 'Measurement',
                issuer: 'Site Team',
            );
        }

        $this->seedDocument(
            actor: $director,
            typeCode: 'TEST_RESULT',
            branch: $project->branch,
            title: 'Subbase material test certificate',
            reference: 'BKH-LAB-SUBBASE-001',
            content: 'Lab certificate placeholder for natural material subbase compliance.',
            links: [[$project::class, $project->id], [$kibogaSite::class, $kibogaSite->id]],
            documentNumber: 'BKH-LAB-SUBBASE-001',
            revision: '0',
            discipline: 'Quality',
            issuer: 'Materials Laboratory',
        );
    }

    /**
     * @param  list<array{0: class-string, 1: string}>  $links
     */
    private function seedDocument(
        User $actor,
        string $typeCode,
        Branch $branch,
        string $title,
        string $reference,
        string $content,
        array $links,
        string $confidentiality = Document::CONFIDENTIALITY_NORMAL,
        ?string $expiresOn = null,
        ?string $documentNumber = null,
        ?string $revision = null,
        ?string $discipline = null,
        ?string $issuer = null,
        string $status = Document::STATUS_ACTIVE,
    ): Document {
        $type = DocumentType::query()->where('code', $typeCode)->firstOrFail();

        $document = Document::query()->updateOrCreate(
            ['tenant_id' => $branch->tenant_id, 'reference' => $reference],
            [
                'branch_id' => $branch->id,
                'document_type_id' => $type->id,
                'owner_id' => $actor->id,
                'title' => $title,
                'description' => $content,
                'document_number' => $documentNumber,
                'revision' => $revision,
                'discipline' => $discipline,
                'issuer' => $issuer,
                'document_date' => now()->toDateString(),
                'received_on' => now()->toDateString(),
                'expires_on' => $expiresOn,
                'confidentiality' => $confidentiality,
                'status' => $status,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $version = $this->seedDocumentVersion($actor, $document, 1, $content);
        $document->forceFill(['current_version_id' => $version->id])->save();

        foreach ($links as [$linkableType, $id]) {
            DocumentLink::query()->updateOrCreate(
                [
                    'document_id' => $document->id,
                    'linkable_type' => $linkableType,
                    'linkable_id' => $id,
                ],
                [
                    'tenant_id' => $document->tenant_id,
                    'created_by' => $actor->id,
                ],
            );
        }

        return $document;
    }

    private function seedDocumentVersion(User $actor, Document $document, int $versionNumber, string $content): DocumentVersion
    {
        $path = sprintf('documents/%s/%s/v%d/seeded.txt', $document->tenant_id, $document->id, $versionNumber);

        Storage::disk('local')->put($path, $content);

        $version = DocumentVersion::query()->updateOrCreate(
            ['document_id' => $document->id, 'version_number' => $versionNumber],
            [
                'tenant_id' => $document->tenant_id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => sprintf('seeded-%s-v%d.txt', $document->reference, $versionNumber),
                'mime_type' => 'text/plain',
                'size_bytes' => mb_strlen($content),
                'checksum' => hash('sha256', $content),
                'notes' => 'Seeded version '.$versionNumber,
                'uploaded_by' => $actor->id,
                'uploaded_at' => now(),
            ],
        );

        $document->forceFill(['current_version_id' => $version->id])->save();

        return $version;
    }
}
