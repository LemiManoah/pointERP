<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\EnsureDefaultTenant;
use App\Actions\Operations\DailySiteReports\PostApprovedDsrEquipmentLines;
use App\Actions\Operations\Inventory\PostInventoryStockMovement;
use App\Actions\Operations\Inventory\ReceiveInventoryStock;
use App\Actions\Operations\Inventory\ReconcileDsrMaterialLine;
use App\Actions\Operations\Inventory\ReconcileInventoryStockCount;
use App\Actions\Operations\Inventory\ReviewInventoryReconciliation;
use App\Actions\Operations\Inventory\ReviewInventoryTransfer;
use App\Actions\Operations\Inventory\TransferInventoryItems;
use App\Enums\DsrMaterialReconciliationStatus;
use App\Enums\EstimateResourceType;
use App\Enums\ExpensePayeeType;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpensePaymentStatus;
use App\Enums\ExpenseStatus;
use App\Enums\InventoryBatchStatus;
use App\Enums\InventoryMaterialClass;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryReservationStatus;
use App\Enums\InventoryStoreType;
use App\Enums\InventoryTrackingType;
use App\Enums\MaterialRequisitionPriority;
use App\Enums\MaterialRequisitionStatus;
use App\Enums\ProjectEstimateStatus;
use App\Enums\PurchaseOrderStatus;
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
use App\Models\DsrExpenseReconciliation;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentFuelTransaction;
use App\Models\EquipmentLocation;
use App\Models\EquipmentLocationConfirmation;
use App\Models\EquipmentMaintenancePartLine;
use App\Models\EquipmentMaintenanceSchedule;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\EquipmentMeterReading;
use App\Models\EquipmentTransfer;
use App\Models\EstimateResourceLine;
use App\Models\ExchangeRate;
use App\Models\ExpectedDailySiteReport;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\ExpenseLine;
use App\Models\ExpensePayment;
use App\Models\InventoryBatch;
use App\Models\InventoryCategory;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryItemPrice;
use App\Models\InventoryPriceTier;
use App\Models\InventoryReservation;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\InventoryUnitConversion;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReportingCalendar;
use App\Models\ReportingCalendarException;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffPosition;
use App\Models\TenantCurrency;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Services\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'PROCUREMENT-OFFICER' => $this->position('PROCUREMENT-OFFICER', 'Procurement Officer'),
            'ACCOUNTANT' => $this->position('ACCOUNTANT', 'Accountant'),
            'STORE-KEEPER' => $this->position('STORE-KEEPER', 'Store Keeper'),
            'CASHIER' => $this->position('CASHIER', 'Cashier'),
            'SITE-ENGINEER' => $this->position('SITE-ENGINEER', 'Site Engineer'),
            'SITE-MANAGER' => $this->position('SITE-MANAGER', 'Site Manager'),
            'AUDITOR' => $this->position('AUDITOR', 'Auditor'),
            'FLEET-MANAGER' => $this->position('FLEET-MANAGER', 'Fleet Manager'),
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
            staffNumber: 'POINT-010',
            name: 'Kampala Procurement Officer',
            email: 'procurement.kla@point.test',
            branch: $branches['KLA-HQ'],
            position: $positions['PROCUREMENT-OFFICER'],
            roleName: 'Procurement Officer',
            branchAccess: [$branches['KLA-HQ']],
            defaultBranch: $branches['KLA-HQ'],
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

        $this->user(
            staffNumber: 'POINT-011',
            name: 'Kampala Cashier',
            email: 'cashier.kla@point.test',
            branch: $branches['KLA-HQ'],
            position: $positions['CASHIER'],
            roleName: 'Cashier',
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

        $this->user(
            staffNumber: 'POINT-009',
            name: 'Regional Fleet Manager',
            email: 'fleet@point.test',
            branch: $branches['KLA-HQ'],
            position: $positions['FLEET-MANAGER'],
            roleName: 'Fleet Manager',
            branchAccess: [$branches['KLA-HQ'], $branches['GUL-SITE'], $branches['JUB-HQ']],
            defaultBranch: $branches['KLA-HQ'],
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

        $this->inventoryDemoData($branches['KLA-HQ'], $branches['GUL-SITE'], $director);
        $this->seedRoadEstimateBaseline($director);
        $this->expenseDemoData($branches['KLA-HQ'], $branches['GUL-SITE'], $director);

        $this->exchangeRate($director, null, 'USD', 'UGX', '3600.0000000000', now()->subMonth()->toDateString(), ExchangeRate::STATUS_APPROVED);
        $this->exchangeRate($director, null, 'USD', 'UGX', '3700.0000000000', now()->toDateString(), ExchangeRate::STATUS_DRAFT);
        $this->exchangeRate($director, $branches['JUB-HQ'], 'USD', 'SSP', '1500.0000000000', now()->toDateString(), ExchangeRate::STATUS_APPROVED);
        $this->exchangeRate($director, $branches['KLA-HQ'], 'UGX', 'USD', '0.0002702703', now()->toDateString(), ExchangeRate::STATUS_DRAFT);
    }

    private function expenseDemoData(Branch $kampalaBranch, Branch $guluBranch, User $director): void
    {
        $tenantId = $director->tenant_id;
        $categories = [];
        foreach ([
            ['UTILITIES', 'Utilities', true, 'Recurring facility services such as electricity, water and internet.'],
            ['SITE-WELFARE', 'Site welfare', false, 'Meals, drinking water and other workforce welfare costs.'],
            ['TRAVEL', 'Travel and accommodation', true, 'Approved transport and accommodation costs.'],
            ['STATUTORY', 'Statutory and permits', true, 'Permits, licences and statutory charges.'],
            ['ADMIN', 'Administration', false, 'Routine office and communication costs.'],
        ] as [$code, $name, $requiresEvidence, $description]) {
            $categories[$code] = ExpenseCategory::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'description' => $description, 'requires_evidence' => $requiresEvidence, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            );
        }

        $items = [];
        foreach ([
            ['YAKA', 'Yaka electricity', 'UTILITIES'],
            ['WATER', 'Water', 'UTILITIES'],
            ['INTERNET', 'Internet subscription', 'UTILITIES'],
            ['SITE-MEALS', 'Site meals', 'SITE-WELFARE'],
            ['DRINKING-WATER', 'Drinking water', 'SITE-WELFARE'],
            ['LOCAL-TRANSPORT', 'Local transport', 'TRAVEL'],
            ['ACCOMMODATION', 'Accommodation', 'TRAVEL'],
            ['PERMIT-FEE', 'Permit fee', 'STATUTORY'],
            ['PRINTING', 'Printing and photocopying', 'ADMIN'],
        ] as [$code, $name, $categoryCode]) {
            $items[$code] = ExpenseItem::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['expense_category_id' => $categories[$categoryCode]->id, 'name' => $name, 'requires_evidence' => false, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            );
        }

        $yakaExpense = Expense::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'expense_number' => 'EXP-DEMO-001'],
            [
                'branch_id' => $kampalaBranch->id,
                'expense_date' => now()->subDays(5)->toDateString(),
                'payee_type' => ExpensePayeeType::Other,
                'payee_name_snapshot' => 'Umeme prepaid electricity',
                'currency_code' => 'UGX',
                'base_currency_code' => 'UGX',
                'exchange_rate' => '1.0000000000',
                'subtotal' => '625000.0000',
                'total_amount' => '625000.0000',
                'base_currency_total' => '625000.0000',
                'description' => 'Electricity tokens for the Kampala office and stores compound.',
                'reference' => 'YAKA-0826-11842',
                'status' => ExpenseStatus::Approved,
                'submitted_by' => $director->id,
                'submitted_at' => now()->subDays(5),
                'approved_by' => $director->id,
                'approved_at' => now()->subDays(4),
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );
        ExpenseLine::query()->updateOrCreate(
            ['expense_id' => $yakaExpense->id, 'expense_item_id' => $items['YAKA']->id],
            ['tenant_id' => $tenantId, 'expense_category_name_snapshot' => 'Utilities', 'expense_item_name_snapshot' => 'Yaka electricity', 'quantity' => '1.0000', 'unit_amount' => '625000.0000', 'amount' => '625000.0000', 'base_currency_amount' => '625000.0000', 'sort_order' => 0],
        );
        ExpensePayment::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'payment_number' => 'PAY-DEMO-001'],
            ['branch_id' => $kampalaBranch->id, 'expense_id' => $yakaExpense->id, 'paid_at' => now()->subDays(4), 'amount' => '200000.0000', 'currency_code' => 'UGX', 'base_currency_amount' => '200000.0000', 'exchange_rate' => '1.0000000000', 'payment_method' => ExpensePaymentMethod::MobileMoney, 'reference' => 'MOMO-DEMO-001', 'notes' => 'Initial part payment.', 'status' => ExpensePaymentStatus::Recorded, 'recorded_by' => $director->id],
        );
        $this->seedDocument(
            actor: $director,
            typeCode: 'EXPENSE_RECEIPT',
            branch: $kampalaBranch,
            title: 'Yaka electricity receipt August 2026',
            reference: 'EXP-RECEIPT-YAKA-0826',
            content: 'Seeded receipt evidence for the Kampala office electricity expense.',
            links: [[$yakaExpense::class, $yakaExpense->id]],
            documentNumber: 'YAKA-0826-11842',
            issuer: 'Umeme',
        );

        $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
        $site = $project->sites()->orderBy('name')->first();
        $siteExpense = Expense::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'expense_number' => 'EXP-DEMO-002'],
            [
                'branch_id' => $guluBranch->id,
                'expense_date' => now()->subDays(2)->toDateString(),
                'payee_type' => ExpensePayeeType::Other,
                'payee_name_snapshot' => 'Busunju site catering team',
                'currency_code' => 'UGX',
                'base_currency_code' => 'UGX',
                'exchange_rate' => '1.0000000000',
                'subtotal' => '450000.0000',
                'total_amount' => '450000.0000',
                'base_currency_total' => '450000.0000',
                'description' => 'Field team meals and allowances recorded from site operations.',
                'reference' => 'SITE-CATERING-0826',
                'status' => ExpenseStatus::Approved,
                'submitted_by' => $director->id,
                'submitted_at' => now()->subDays(2),
                'approved_by' => $director->id,
                'approved_at' => now()->subDay(),
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );
        $siteExpenseLine = ExpenseLine::query()->updateOrCreate(
            ['expense_id' => $siteExpense->id, 'expense_item_id' => $items['SITE-MEALS']->id],
            ['tenant_id' => $tenantId, 'project_id' => $project->id, 'site_id' => $site?->id, 'expense_category_name_snapshot' => 'Site welfare', 'expense_item_name_snapshot' => 'Site meals', 'quantity' => '1.0000', 'unit_amount' => '450000.0000', 'amount' => '450000.0000', 'base_currency_amount' => '450000.0000', 'sort_order' => 0],
        );
        $dsrCost = DailySiteReportCostLine::query()
            ->whereHas('report', fn (Builder $query): Builder => $query->where('project_id', $project->id))
            ->where('description', 'Field team allowances')
            ->first();
        if ($dsrCost instanceof DailySiteReportCostLine) {
            DsrExpenseReconciliation::query()->updateOrCreate(
                ['daily_site_report_cost_line_id' => $dsrCost->id],
                ['tenant_id' => $tenantId, 'expense_line_id' => $siteExpenseLine->id, 'reconciled_by' => $director->id, 'reconciled_at' => now()->subDay(), 'reason' => 'Replace the provisional DSR allowance with the approved expense evidence.'],
            );
        }

        $draftExpense = Expense::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'expense_number' => 'EXP-DEMO-003'],
            ['branch_id' => $kampalaBranch->id, 'expense_date' => now()->toDateString(), 'payee_type' => ExpensePayeeType::Other, 'payee_name_snapshot' => 'Office internet provider', 'currency_code' => 'UGX', 'base_currency_code' => 'UGX', 'exchange_rate' => '1.0000000000', 'subtotal' => '180000.0000', 'total_amount' => '180000.0000', 'base_currency_total' => '180000.0000', 'description' => 'Draft monthly internet subscription awaiting an invoice.', 'status' => ExpenseStatus::Draft, 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        ExpenseLine::query()->updateOrCreate(
            ['expense_id' => $draftExpense->id, 'expense_item_id' => $items['INTERNET']->id],
            ['tenant_id' => $tenantId, 'expense_category_name_snapshot' => 'Utilities', 'expense_item_name_snapshot' => 'Internet subscription', 'quantity' => '1.0000', 'unit_amount' => '180000.0000', 'amount' => '180000.0000', 'base_currency_amount' => '180000.0000', 'sort_order' => 0],
        );
    }

    private function inventoryDemoData(Branch $kampalaBranch, Branch $guluBranch, User $director): void
    {
        $tenantId = resolve(TenantContext::class)->id();
        $category = InventoryCategory::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'ROAD-MATERIALS'],
            ['name' => 'Road construction materials', 'description' => 'Core materials used on road and drainage works.', 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        $units = [];
        foreach ([['KG', 'Kilogram', 'kg', 'mass', true], ['BAG', 'Bag', 'bag', 'count', false], ['TONNE', 'Tonne', 't', 'mass', false], ['LITRE', 'Litre', 'L', 'volume', true], ['PIECE', 'Piece', 'pc', 'count', true]] as [$code, $name, $symbol, $dimension, $base]) {
            $units[$code] = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'symbol' => $symbol, 'quantity_dimension' => $dimension, 'is_base_unit' => $base, 'is_active' => true],
            );
        }

        $supplier = Customer::query()->where('tenant_id', $tenantId)->where('code', 'SUP-DEMO')->first();
        if ($supplier === null) {
            $supplier = Customer::query()->create(['tenant_id' => $tenantId, 'branch_id' => $kampalaBranch->id, 'type' => Customer::TYPE_SUPPLIER, 'name' => 'Demo Materials Supplier', 'code' => 'SUP-DEMO', 'status' => 'active', 'created_by' => $director->id, 'updated_by' => $director->id]);
        }

        $items = [
            ['CEM-42', 'Portland cement 42.5N', 'BAG', InventoryMaterialClass::ConstructionMaterial, InventoryTrackingType::Batch, 'CEM-42-2026-08', true, true, '250', '500', '42000', '48000'],
            ['CEM-PILOT', 'Pilot rapid-setting cement', 'BAG', InventoryMaterialClass::ConstructionMaterial, InventoryTrackingType::Batch, 'CEM-PILOT-2026-08', true, false, '100', '250', '45000', null],
            ['AGG-20', 'Crushed aggregate 20mm', 'TONNE', InventoryMaterialClass::ConstructionMaterial, InventoryTrackingType::None, null, false, true, '25', '100', '120000', '145000'],
            ['PPE-VEST', 'High visibility safety vest', 'PIECE', InventoryMaterialClass::Consumable, InventoryTrackingType::None, null, false, false, '20', '50', '18000', null],
        ];
        $inventoryItems = [];
        foreach ($items as [$code, $name, $unitCode, $class, $trackingType, $batchNumber, $isExpires, $isForSale, $reorder, $reorderQty, $unitCost, $sellingPrice]) {
            /** @var InventoryMaterialClass::ConstructionMaterial|InventoryMaterialClass::Consumable $class */
            /** @var InventoryTrackingType::Batch|InventoryTrackingType::None $trackingType */
            $inventoryItems[$code] = InventoryItem::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['inventory_category_id' => $category->id, 'stock_unit_id' => $units[$unitCode]->id, 'preferred_supplier_id' => $supplier->id, 'name' => $name, 'material_class' => $class->value, 'tracking_type' => $trackingType->value, 'batch_number' => $batchNumber, 'is_expires' => $isExpires, 'is_for_sale' => $isForSale, 'minimum_stock' => $reorder, 'reorder_quantity' => $reorderQty, 'default_unit_cost' => $unitCost, 'default_selling_price' => $sellingPrice, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            );
        }

        $location = EquipmentLocation::query()->where('tenant_id', $tenantId)->where('branch_id', $kampalaBranch->id)->where('type', 'depot')->first();
        $kampalaStore = InventoryStore::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'KLA-MAIN-STORE'],
            ['branch_id' => $kampalaBranch->id, 'equipment_location_id' => $location?->id, 'name' => 'Kampala Main Materials Store', 'type' => InventoryStoreType::Depot->value, 'address' => 'Kampala Head Office depot', 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        $guluStore = InventoryStore::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'GUL-SITE-STORE'],
            ['branch_id' => $guluBranch->id, 'name' => 'Gulu Road Site Store', 'type' => InventoryStoreType::SiteStore->value, 'address' => 'Gulu project compound store', 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
        );

        InventoryUnitConversion::query()->updateOrCreate(
            ['inventory_item_id' => $inventoryItems['CEM-42']->id, 'from_unit_id' => $units['KG']->id, 'to_unit_id' => $units['BAG']->id],
            ['tenant_id' => $tenantId, 'multiplier' => '0.0200000000', 'divisor' => 1, 'effective_from' => now()->startOfYear()->toDateString(), 'reason' => 'One 50 kg cement bag is the stock unit.', 'is_active' => true, 'created_by' => $director->id],
        );
        $retailTier = InventoryPriceTier::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'RETAIL'],
            ['name' => 'Retail', 'description' => 'Standard counter selling price.', 'priority' => 100, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        $wholesaleTier = InventoryPriceTier::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'WHOLESALE'],
            ['name' => 'Wholesale', 'description' => 'Bulk customer selling price.', 'priority' => 50, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        foreach ([[$retailTier, '48000', '1'], [$wholesaleTier, '45000', '100']] as [$tier, $amount, $minimumQuantity]) {
            /** @var InventoryPriceTier $tier */
            InventoryItemPrice::query()->updateOrCreate(
                ['inventory_item_id' => $inventoryItems['CEM-42']->id, 'inventory_price_tier_id' => $tier->id, 'branch_id' => $kampalaBranch->id, 'unit_of_measure_id' => $units['BAG']->id],
                ['tenant_id' => $tenantId, 'amount' => $amount, 'minimum_quantity' => $minimumQuantity, 'effective_from' => now()->startOfYear()->toDateString(), 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            );
        }

        $cementBatch = InventoryBatch::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'inventory_item_id' => $inventoryItems['CEM-42']->id, 'batch_number' => 'CEM-42-2026-08'],
            ['inventory_store_id' => $kampalaStore->id, 'manufactured_on' => now()->subMonth()->toDateString(), 'expires_on' => now()->addMonths(5)->toDateString(), 'status' => InventoryBatchStatus::Available->value, 'notes' => 'Demo cement batch for the Kampala depot.', 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        foreach ([[$kampalaStore, '250', '500', 'Aisle A / Cement bay'], [$guluStore, '100', '250', 'Weatherproof container 1']] as [$store, $minimumStock, $reorderQuantity, $storageLocation]) {
            /** @var InventoryStore $store */
            InventoryStoreItem::query()->updateOrCreate(
                ['inventory_store_id' => $store->id, 'inventory_item_id' => $inventoryItems['CEM-42']->id],
                ['tenant_id' => $tenantId, 'minimum_stock' => $minimumStock, 'reorder_quantity' => $reorderQuantity, 'storage_location' => $storageLocation, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            );
        }

        foreach ([['AGG-20', '25', '100', 'Aggregate yard'], ['PPE-VEST', '20', '50', 'PPE cage']] as [$itemCode, $minimumStock, $reorderQuantity, $storageLocation]) {
            InventoryStoreItem::query()->updateOrCreate(
                ['inventory_store_id' => $kampalaStore->id, 'inventory_item_id' => $inventoryItems[$itemCode]->id],
                ['tenant_id' => $tenantId, 'minimum_stock' => $minimumStock, 'reorder_quantity' => $reorderQuantity, 'storage_location' => $storageLocation, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            );
        }

        foreach ([[$kampalaStore, '100', '250', 'Pilot receipt bay'], [$guluStore, '100', '250', 'Project store container 2']] as [$store, $minimumStock, $reorderQuantity, $storageLocation]) {
            /** @var InventoryStore $store */
            InventoryStoreItem::query()->updateOrCreate(
                ['inventory_store_id' => $store->id, 'inventory_item_id' => $inventoryItems['CEM-PILOT']->id],
                ['tenant_id' => $tenantId, 'minimum_stock' => $minimumStock, 'reorder_quantity' => $reorderQuantity, 'storage_location' => $storageLocation, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            );
        }

        $stockPosting = resolve(PostInventoryStockMovement::class);
        foreach ([
            [$inventoryItems['CEM-42'], $kampalaStore, $units['BAG'], $cementBatch, '1200', 'seed:opening:cem-42:kla'],
            [$inventoryItems['AGG-20'], $kampalaStore, $units['TONNE'], null, '180', 'seed:opening:agg-20:kla'],
            [$inventoryItems['PPE-VEST'], $kampalaStore, $units['PIECE'], null, '75', 'seed:opening:ppe-vest:kla'],
        ] as [$item, $store, $unit, $batch, $quantity, $sourceKey]) {
            /** @var InventoryItem $item */
            /** @var InventoryStore $store */
            /** @var UnitOfMeasure $unit */
            /** @var InventoryBatch|null $batch */
            $stockPosting->handle($store, $item, [
                'movement_type' => InventoryMovementType::OpeningBalance->value,
                'original_quantity' => $quantity,
                'original_unit_id' => $unit->id,
                'inventory_batch_id' => $batch?->id,
                'source_key' => $sourceKey,
                'reason' => 'Controlled opening balance for Phase 3B demonstration.',
            ], $director);
        }

        $approvedRoadReport = DailySiteReport::query()
            ->where('branch_id', $guluBranch->id)
            ->where('status', DailySiteReport::STATUS_APPROVED)
            ->first();
        if ($approvedRoadReport instanceof DailySiteReport) {
            DailySiteReportMaterialLine::query()->updateOrCreate(
                [
                    'daily_site_report_id' => $approvedRoadReport->id,
                    'delivery_reference' => 'DSR-CEMENT-DEMO',
                ],
                [
                    'tenant_id' => $tenantId,
                    'branch_id' => $guluBranch->id,
                    'inventory_item_id' => $inventoryItems['CEM-42']->id,
                    'inventory_store_id' => $guluStore->id,
                    'unit_of_measure_id' => $units['BAG']->id,
                    'conversion_multiplier' => '1.0000000000',
                    'stock_unit_quantity' => '25.0000',
                    'inventory_reconciliation_status' => DsrMaterialReconciliationStatus::Pending->value,
                    'material_name' => $inventoryItems['CEM-42']->name,
                    'material_type' => 'used',
                    'quantity' => '25.0000',
                    'unit' => $units['BAG']->symbol,
                    'currency_code' => 'UGX',
                    'sort_order' => 2,
                ],
            );
        }

        $guluRequester = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
        $guluProject = Project::query()->where('branch_id', $guluBranch->id)->first();
        $guluSite = $guluProject?->sites()->first();
        $submitted = MaterialRequisition::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'reference' => 'MR-DEMO-GULU'],
            ['branch_id' => $guluBranch->id, 'inventory_store_id' => $guluStore->id, 'requesting_user_id' => $guluRequester->id, 'project_id' => $guluProject?->id, 'site_id' => $guluSite?->id, 'department' => 'Drainage works', 'required_by_date' => now()->addDays(3)->toDateString(), 'priority' => MaterialRequisitionPriority::High, 'status' => MaterialRequisitionStatus::Submitted, 'reason' => 'Cement required for the next drainage structure pour.', 'submitted_by' => $guluRequester->id, 'submitted_at' => now(), 'created_by' => $guluRequester->id, 'updated_by' => $guluRequester->id],
        );
        MaterialRequisitionLine::query()->updateOrCreate(
            ['material_requisition_id' => $submitted->id, 'inventory_item_id' => $inventoryItems['CEM-42']->id],
            ['tenant_id' => $tenantId, 'unit_of_measure_id' => $units['BAG']->id, 'item_code_snapshot' => 'CEM-42', 'item_name_snapshot' => 'Portland cement 42.5N', 'unit_code_snapshot' => 'BAG', 'unit_symbol_snapshot' => 'bag', 'requested_quantity' => '80.0000', 'conversion_multiplier' => '1.0000000000', 'stock_quantity' => '80.0000', 'purpose' => 'Culvert headwalls', 'sort_order' => 0],
        );

        $kampalaRequester = User::query()->where('email', 'admin.kla@point.test')->firstOrFail();
        $approved = MaterialRequisition::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'reference' => 'MR-DEMO-KLA'],
            ['branch_id' => $kampalaBranch->id, 'inventory_store_id' => $kampalaStore->id, 'requesting_user_id' => $kampalaRequester->id, 'department' => 'Site safety', 'required_by_date' => now()->addDay()->toDateString(), 'priority' => MaterialRequisitionPriority::Urgent, 'status' => MaterialRequisitionStatus::Approved, 'reason' => 'Issue safety vests to the incoming concrete crew.', 'submitted_by' => $kampalaRequester->id, 'submitted_at' => now()->subHour(), 'approved_by' => $director->id, 'approved_at' => now(), 'reviewed_by' => $director->id, 'reviewed_at' => now(), 'created_by' => $kampalaRequester->id, 'updated_by' => $director->id],
        );
        $approvedLine = MaterialRequisitionLine::query()->updateOrCreate(
            ['material_requisition_id' => $approved->id, 'inventory_item_id' => $inventoryItems['PPE-VEST']->id],
            ['tenant_id' => $tenantId, 'unit_of_measure_id' => $units['PIECE']->id, 'item_code_snapshot' => 'PPE-VEST', 'item_name_snapshot' => 'High visibility safety vest', 'unit_code_snapshot' => 'PIECE', 'unit_symbol_snapshot' => 'pc', 'requested_quantity' => '25.0000', 'conversion_multiplier' => '1.0000000000', 'stock_quantity' => '25.0000', 'approved_quantity' => '25.0000', 'purpose' => 'New crew mobilisation', 'sort_order' => 0],
        );
        InventoryReservation::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'source_type' => MaterialRequisitionLine::class, 'source_id' => $approvedLine->id, 'inventory_item_id' => $inventoryItems['PPE-VEST']->id],
            ['branch_id' => $kampalaBranch->id, 'inventory_store_id' => $kampalaStore->id, 'reserved_quantity' => '25.0000', 'issued_quantity' => '0.0000', 'released_quantity' => '0.0000', 'status' => InventoryReservationStatus::Active, 'created_by' => $director->id, 'updated_by' => $director->id],
        );

        $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'order_number' => 'PO-2026-DEMO01'],
            ['branch_id' => $kampalaBranch->id, 'inventory_store_id' => $kampalaStore->id, 'supplier_id' => $supplier->id, 'order_number' => 'PO-2026-DEMO01', 'supplier_name_snapshot' => $supplier->name, 'supplier_code_snapshot' => $supplier->code, 'order_date' => now()->subDay()->toDateString(), 'expected_date' => now()->addDays(2)->toDateString(), 'currency_code' => 'UGX', 'status' => PurchaseOrderStatus::Approved, 'subtotal' => '450000.0000', 'discount_amount' => '0.0000', 'tax_amount' => '0.0000', 'total_amount' => '450000.0000', 'delivery_terms' => 'Delivery to Kampala Main Materials Store.', 'payment_terms' => 'Payment after accepted delivery.', 'submitted_by' => $director->id, 'submitted_at' => now()->subDay(), 'approved_by' => $director->id, 'approved_at' => now()->subDay(), 'reviewed_by' => $director->id, 'reviewed_at' => now()->subDay(), 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        PurchaseOrderLine::query()->updateOrCreate(
            ['purchase_order_id' => $purchaseOrder->id, 'inventory_item_id' => $inventoryItems['PPE-VEST']->id],
            ['tenant_id' => $tenantId, 'unit_of_measure_id' => $units['PIECE']->id, 'item_code_snapshot' => 'PPE-VEST', 'item_name_snapshot' => 'High visibility safety vest', 'unit_code_snapshot' => 'PIECE', 'unit_symbol_snapshot' => 'pc', 'ordered_quantity' => '25.0000', 'conversion_multiplier' => '1.0000000000', 'stock_quantity' => '25.0000', 'unit_price' => '18000.0000', 'price_source' => 'recorded_cost', 'line_amount' => '450000.0000', 'accepted_quantity' => '0.0000', 'rejected_quantity' => '0.0000', 'cancelled_quantity' => '0.0000', 'sort_order' => 0],
        );
        $pilotOrder = PurchaseOrder::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'order_number' => 'PO-2026-PILOT01'],
            ['branch_id' => $kampalaBranch->id, 'inventory_store_id' => $kampalaStore->id, 'supplier_id' => $supplier->id, 'supplier_name_snapshot' => $supplier->name, 'supplier_code_snapshot' => $supplier->code, 'order_date' => now()->subDays(14)->toDateString(), 'expected_date' => now()->subDays(5)->toDateString(), 'currency_code' => 'UGX', 'status' => PurchaseOrderStatus::Approved, 'subtotal' => '21450000.0000', 'discount_amount' => '0.0000', 'tax_amount' => '0.0000', 'total_amount' => '21450000.0000', 'delivery_terms' => 'Delivery to Kampala Main Materials Store.', 'payment_terms' => 'Payment after accepted delivery.', 'submitted_by' => $director->id, 'submitted_at' => now()->subDays(14), 'approved_by' => $director->id, 'approved_at' => now()->subDays(13), 'reviewed_by' => $director->id, 'reviewed_at' => now()->subDays(13), 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        $cementOrderLine = PurchaseOrderLine::query()->updateOrCreate(
            ['purchase_order_id' => $pilotOrder->id, 'inventory_item_id' => $inventoryItems['CEM-PILOT']->id],
            ['tenant_id' => $tenantId, 'unit_of_measure_id' => $units['BAG']->id, 'item_code_snapshot' => 'CEM-PILOT', 'item_name_snapshot' => 'Pilot rapid-setting cement', 'unit_code_snapshot' => 'BAG', 'unit_symbol_snapshot' => 'bag', 'ordered_quantity' => '500.0000', 'conversion_multiplier' => '1.0000000000', 'stock_quantity' => '500.0000', 'unit_price' => '42000.0000', 'price_source' => 'recorded_cost', 'line_amount' => '21000000.0000', 'accepted_quantity' => '0.0000', 'rejected_quantity' => '0.0000', 'cancelled_quantity' => '0.0000', 'sort_order' => 1],
        );
        $pilotPpeOrderLine = PurchaseOrderLine::query()->updateOrCreate(
            ['purchase_order_id' => $pilotOrder->id, 'inventory_item_id' => $inventoryItems['PPE-VEST']->id],
            ['tenant_id' => $tenantId, 'unit_of_measure_id' => $units['PIECE']->id, 'item_code_snapshot' => 'PPE-VEST', 'item_name_snapshot' => 'High visibility safety vest', 'unit_code_snapshot' => 'PIECE', 'unit_symbol_snapshot' => 'pc', 'ordered_quantity' => '25.0000', 'conversion_multiplier' => '1.0000000000', 'stock_quantity' => '25.0000', 'unit_price' => '18000.0000', 'price_source' => 'recorded_cost', 'line_amount' => '450000.0000', 'accepted_quantity' => '0.0000', 'rejected_quantity' => '0.0000', 'cancelled_quantity' => '0.0000', 'sort_order' => 0],
        );

        $receipt = InventoryGoodsReceipt::query()->where('purchase_order_id', $pilotOrder->id)->where('supplier_reference', 'DEMO-DELIVERY-001')->first();
        if (! $receipt instanceof InventoryGoodsReceipt) {
            $receipt = resolve(ReceiveInventoryStock::class)->handle([
                'purchase_order_id' => $pilotOrder->id,
                'supplier_reference' => 'DEMO-DELIVERY-001',
                'received_on' => now()->subDays(7)->toDateString(),
                'notes' => 'Pilot delivery: usable stock accepted and damaged quantities rejected at inspection.',
                'lines' => [
                    ['purchase_order_line_id' => $cementOrderLine->id, 'quantity' => '310', 'accepted_quantity' => '300', 'rejected_quantity' => '10', 'rejection_reason' => 'Ten bags were torn and water damaged.', 'batch_number' => 'CEM-PILOT-DELIVERY', 'manufactured_on' => now()->subMonth()->toDateString(), 'expires_on' => now()->addMonths(5)->toDateString()],
                    ['purchase_order_line_id' => $pilotPpeOrderLine->id, 'quantity' => '12', 'accepted_quantity' => '10', 'rejected_quantity' => '2', 'rejection_reason' => 'Reflective strips were detached.'],
                ],
            ], $director);
        }

        foreach ([$cementOrderLine, $pilotPpeOrderLine] as $orderLine) {
            $orderLine->forceFill([
                'accepted_quantity' => (string) $receipt->lines()->where('purchase_order_line_id', $orderLine->id)->sum('accepted_quantity'),
                'rejected_quantity' => (string) $receipt->lines()->where('purchase_order_line_id', $orderLine->id)->sum('rejected_quantity'),
            ])->save();
        }

        $pilotOrder->forceFill(['status' => PurchaseOrderStatus::PartiallyReceived])->save();

        $transferBatch = InventoryBatch::query()->where('batch_number', 'CEM-PILOT-DELIVERY')->firstOrFail();
        $transfer = resolve(TransferInventoryItems::class)->handle($kampalaStore, $guluStore, [
            'transfer_key' => 'seed:transfer:cement:kla-gulu',
            'reason' => 'Move accepted cement to the road project store.',
            'lines' => [['inventory_item_id' => $inventoryItems['CEM-PILOT']->id, 'unit_of_measure_id' => $units['BAG']->id, 'inventory_batch_id' => $transferBatch->id, 'quantity' => '150']],
        ], $director);
        if ($transfer->status->value === 'pending_approval') {
            resolve(ReviewInventoryTransfer::class)->approve($transfer, $director);
        }

        $pilotRequisition = MaterialRequisition::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'reference' => 'MR-PILOT-GULU'],
            ['branch_id' => $guluBranch->id, 'inventory_store_id' => $guluStore->id, 'requesting_user_id' => $guluRequester->id, 'project_id' => $guluProject?->id, 'site_id' => $guluSite?->id, 'department' => 'Drainage works', 'required_by_date' => now()->subDays(2)->toDateString(), 'priority' => MaterialRequisitionPriority::High, 'status' => MaterialRequisitionStatus::PartiallyIssued, 'reason' => 'Pilot issue for the drainage structure pour.', 'submitted_by' => $guluRequester->id, 'submitted_at' => now()->subDays(3), 'approved_by' => $director->id, 'approved_at' => now()->subDays(2), 'reviewed_by' => $director->id, 'reviewed_at' => now()->subDays(2), 'created_by' => $guluRequester->id, 'updated_by' => $director->id],
        );
        $submittedLine = MaterialRequisitionLine::query()->updateOrCreate(
            ['material_requisition_id' => $pilotRequisition->id, 'inventory_item_id' => $inventoryItems['CEM-PILOT']->id],
            ['tenant_id' => $tenantId, 'unit_of_measure_id' => $units['BAG']->id, 'item_code_snapshot' => 'CEM-PILOT', 'item_name_snapshot' => 'Pilot rapid-setting cement', 'unit_code_snapshot' => 'BAG', 'unit_symbol_snapshot' => 'bag', 'requested_quantity' => '80.0000', 'conversion_multiplier' => '1.0000000000', 'stock_quantity' => '80.0000', 'approved_quantity' => '80.0000', 'issued_quantity' => '25.0000', 'purpose' => 'Pilot culvert headwalls', 'sort_order' => 0],
        );
        InventoryReservation::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'source_type' => MaterialRequisitionLine::class, 'source_id' => $submittedLine->id, 'inventory_item_id' => $inventoryItems['CEM-PILOT']->id],
            ['branch_id' => $guluBranch->id, 'inventory_store_id' => $guluStore->id, 'reserved_quantity' => '80.0000', 'issued_quantity' => '25.0000', 'released_quantity' => '0.0000', 'status' => InventoryReservationStatus::PartiallyIssued, 'created_by' => $director->id, 'updated_by' => $director->id],
        );
        $issue = $stockPosting->handle($guluStore, $inventoryItems['CEM-PILOT'], [
            'movement_type' => InventoryMovementType::Issue->value, 'original_quantity' => '25', 'original_unit_id' => $units['BAG']->id,
            'inventory_batch_id' => $transferBatch->id, 'source_type' => MaterialRequisitionLine::class, 'source_id' => $submittedLine->id,
            'source_key' => 'seed:issue:mr-demo-gulu:cement', 'project_id' => $guluProject?->id, 'site_id' => $guluSite?->id,
            'reason' => 'Partial issue for the drainage-works requisition.',
        ], $director);

        if ($approvedRoadReport instanceof DailySiteReport) {
            $reconciliation = resolve(ReconcileDsrMaterialLine::class);
            $reconciledLine = DailySiteReportMaterialLine::query()->updateOrCreate(
                ['daily_site_report_id' => $approvedRoadReport->id, 'delivery_reference' => 'DSR-CEMENT-RECONCILED'],
                ['tenant_id' => $tenantId, 'branch_id' => $guluBranch->id, 'inventory_item_id' => $inventoryItems['CEM-PILOT']->id, 'inventory_store_id' => $guluStore->id, 'unit_of_measure_id' => $units['BAG']->id, 'conversion_multiplier' => '1.0000000000', 'stock_unit_quantity' => '25.0000', 'inventory_reconciliation_status' => DsrMaterialReconciliationStatus::Pending, 'material_name' => $inventoryItems['CEM-PILOT']->name, 'material_type' => 'used', 'quantity' => '25.0000', 'unit' => 'bag', 'currency_code' => 'UGX', 'sort_order' => 5],
            );
            if ($reconciledLine->reconciliations()->doesntExist()) {
                $reconciliation->allocate($reconciledLine, $issue, '25', 'Match the approved DSR usage to the project-store requisition issue.', $director);
            }

            $partialLine = DailySiteReportMaterialLine::query()->updateOrCreate(
                ['daily_site_report_id' => $approvedRoadReport->id, 'delivery_reference' => 'DSR-CEMENT-PARTIAL'],
                ['tenant_id' => $tenantId, 'branch_id' => $guluBranch->id, 'inventory_item_id' => $inventoryItems['CEM-PILOT']->id, 'inventory_store_id' => $guluStore->id, 'unit_of_measure_id' => $units['BAG']->id, 'conversion_multiplier' => '1.0000000000', 'stock_unit_quantity' => '40.0000', 'inventory_reconciliation_status' => DsrMaterialReconciliationStatus::Pending, 'material_name' => $inventoryItems['CEM-PILOT']->name, 'material_type' => 'used', 'quantity' => '40.0000', 'unit' => 'bag', 'currency_code' => 'UGX', 'sort_order' => 3],
            );
            if ($partialLine->reconciliations()->doesntExist()) {
                $reconciliation->directIssue($partialLine, ['inventory_store_id' => $guluStore->id, 'inventory_batch_id' => $transferBatch->id, 'quantity' => '15', 'reason' => 'Only part of the reported usage has store evidence so far.'], $director);
            }

            $externalLine = DailySiteReportMaterialLine::query()->updateOrCreate(
                ['daily_site_report_id' => $approvedRoadReport->id, 'delivery_reference' => 'DSR-EXTERNAL-DEMO'],
                ['tenant_id' => $tenantId, 'branch_id' => $guluBranch->id, 'inventory_reconciliation_status' => DsrMaterialReconciliationStatus::NotLinked, 'material_name' => 'Subcontractor-supplied timber formwork', 'material_type' => 'used', 'quantity' => '12.0000', 'unit' => 'piece', 'currency_code' => 'UGX', 'sort_order' => 4],
            );
            if ($externalLine->reconciliations()->doesntExist()) {
                $reconciliation->markExternal($externalLine, 'Supplied and controlled directly by the drainage subcontractor.', $director);
            }
        }

        $count = resolve(ReconcileInventoryStockCount::class)->handle($kampalaStore, [
            'count_key' => 'seed:reconciliation:ppe:kla', 'reason' => 'One damaged vest identified during the pilot physical count.',
            'lines' => [['inventory_item_id' => $inventoryItems['PPE-VEST']->id, 'inventory_batch_id' => null, 'counted_quantity' => '84']],
        ], $director);
        if ($count->status->value === 'pending_approval') {
            resolve(ReviewInventoryReconciliation::class)->approve($count, $director);
        }

        $itemDocument = Document::query()->where('tenant_id', $tenantId)->first();
        if ($itemDocument instanceof Document) {
            DocumentLink::query()->updateOrCreate(
                ['document_id' => $itemDocument->id, 'linkable_type' => InventoryItem::class, 'linkable_id' => $inventoryItems['CEM-42']->id],
                ['tenant_id' => $tenantId, 'created_by' => $director->id],
            );
        }
    }

    private function seedRoadEstimateBaseline(User $actor): void
    {
        $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
        $tenantId = $project->tenant_id;
        $unitDefinitions = [
            'month' => ['MONTH', 'Month', 'month', 'time'],
            'm3' => ['M3', 'Cubic metre', 'm3', 'volume'],
        ];
        $plans = [
            '12.03(a)' => ['36.0000', '4000000.0000', '2800000.0000'],
            '31.01(b)(i)' => ['12000.0000', '8500.0000', '5900.0000'],
            '36.01(a)' => ['20000.0000', '12000.0000', '8200.0000'],
            '37.02(c)' => ['30000.0000', '95000.0000', '71000.0000'],
        ];

        $estimate = ProjectEstimate::query()->updateOrCreate(
            ['project_id' => $project->id, 'version_number' => 1],
            [
                'tenant_id' => $tenantId,
                'branch_id' => $project->branch_id,
                'title' => 'BKH Road approved execution estimate',
                'currency_code' => $project->base_currency_code,
                'status' => ProjectEstimateStatus::Approved,
                'is_baseline' => true,
                'notes' => 'Demo baseline separating measurable work from materials, labour and equipment resources.',
                'approved_by' => $actor->id,
                'approved_at' => now()->subMonths(2),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $activeKeys = [];
        foreach ($plans as $boqReference => [$quantity, $sellingRate, $unitCost]) {
            $activity = ProjectActivity::query()->where('project_id', $project->id)->where('boq_item_number', $boqReference)->firstOrFail();
            $definition = $unitDefinitions[(string) $activity->unit] ?? $unitDefinitions['m3'];
            [$unitCode, $unitName, $symbol, $dimension] = $definition;
            $unit = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $unitCode],
                ['name' => $unitName, 'symbol' => $symbol, 'quantity_dimension' => $dimension, 'is_base_unit' => false, 'is_active' => true],
            );
            $workItemKey = $activity->estimate_work_item_key ?? Str::uuid()->toString();
            $activeKeys[] = $workItemKey;

            $line = ProjectEstimateLine::query()->updateOrCreate(
                ['project_estimate_id' => $estimate->id, 'work_item_key' => $workItemKey],
                [
                    'tenant_id' => $tenantId,
                    'site_id' => $activity->site_id,
                    'unit_of_measure_id' => $unit->id,
                    'boq_reference' => $boqReference,
                    'code' => $activity->code,
                    'name' => $activity->name,
                    'planned_quantity' => $quantity,
                    'selling_rate' => $sellingRate,
                    'estimated_unit_cost' => $unitCost,
                    'sort_order' => $activity->sort_order,
                ],
            );

            $activity->forceFill([
                'estimate_line_id' => $line->id,
                'estimate_work_item_key' => $workItemKey,
                'planned_quantity' => $quantity,
                'rate_amount' => $sellingRate,
                'estimated_unit_cost' => $unitCost,
                'status' => 'active',
            ])->save();
        }

        ProjectEstimateLine::query()->where('project_estimate_id', $estimate->id)->whereNotIn('work_item_key', $activeKeys)->delete();
        ProjectActivity::query()->where('project_id', $project->id)->whereIn('code', ['PETROL', 'EXCAVATOR'])->update(['status' => 'inactive']);

        $topsoilLine = ProjectEstimateLine::query()->where('project_estimate_id', $estimate->id)->where('boq_reference', '31.01(b)(i)')->firstOrFail();
        $hour = UnitOfMeasure::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'HOUR'],
            ['name' => 'Hour', 'symbol' => 'hr', 'quantity_dimension' => 'time', 'is_base_unit' => true, 'is_active' => true],
        );
        EstimateResourceLine::query()->updateOrCreate(
            ['project_estimate_line_id' => $topsoilLine->id, 'resource_type' => EstimateResourceType::Equipment->value, 'name' => 'Excavator'],
            ['tenant_id' => $tenantId, 'unit_of_measure_id' => $hour->id, 'quantity_per_work_unit' => '0.020000', 'estimated_unit_cost' => '180000.0000', 'sort_order' => 0],
        );

        $subbaseLine = ProjectEstimateLine::query()->where('project_estimate_id', $estimate->id)->where('boq_reference', '37.02(c)')->firstOrFail();
        $aggregate = InventoryItem::query()->where('code', 'AGG-20')->firstOrFail();
        EstimateResourceLine::query()->updateOrCreate(
            ['project_estimate_line_id' => $subbaseLine->id, 'resource_type' => EstimateResourceType::Material->value, 'inventory_item_id' => $aggregate->id],
            ['tenant_id' => $tenantId, 'unit_of_measure_id' => $aggregate->stock_unit_id, 'name' => $aggregate->name, 'quantity_per_work_unit' => '1.150000', 'estimated_unit_cost' => $aggregate->default_unit_cost, 'sort_order' => 0],
        );
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
                'address' => 'Kampala, Uganda',
                'status' => 'active',
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );

        $subcontractor = Customer::query()->updateOrCreate(
            ['tenant_id' => $ugandaBranch->tenant_id, 'code' => 'ATSGSL'],
            [
                'branch_id' => $ugandaBranch->id,
                'type' => Customer::TYPE_SUBCONTRACTOR,
                'name' => 'Abubaker Technical Services and General Supplies Limited',
                'email' => null,
                'phone' => null,
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
        $this->seedOperationalControls($director, $ugandaProjectManager, $ugandaSiteEngineer, $roadProject, $busunjuSite, $kibogaSite);

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

        $this->seedEquipmentRegister(
            director: $director,
            ugandaBranch: $ugandaBranch,
            southSudanBranch: $southSudanBranch,
            ugandaProject: $roadProject,
            southSudanProject: $southSudanProject,
            busunjuSite: $busunjuSite,
            kibogaSite: $kibogaSite,
            jubaSite: $jubaSite,
            subcontractor: $subcontractor,
            ugandaProjectManager: $ugandaProjectManager,
            ugandaSiteEngineer: $ugandaSiteEngineer,
        );
    }

    private function seedEquipmentRegister(
        User $director,
        Branch $ugandaBranch,
        Branch $southSudanBranch,
        Project $ugandaProject,
        Project $southSudanProject,
        Site $busunjuSite,
        Site $kibogaSite,
        Site $jubaSite,
        Customer $subcontractor,
        User $ugandaProjectManager,
        User $ugandaSiteEngineer,
    ): void {
        $categories = [];
        foreach ([
            ['EARTHWORK', 'Earthmoving Plant', 'engine_hours', 'tonnes', 'litres_per_hour', '22.0000'],
            ['COMPACTION', 'Compaction Plant', 'engine_hours', 'tonnes', 'litres_per_hour', '14.0000'],
            ['HAULAGE', 'Haulage Vehicles', 'odometer_km', 'tonnes', 'litres_per_100km', '38.0000'],
            ['SUPPORT', 'Site Support Equipment', 'engine_hours', 'kVA', 'litres_per_hour', '8.0000'],
        ] as [$code, $name, $meter, $unit, $basis, $efficiency]) {
            $categories[$code] = EquipmentCategory::query()->updateOrCreate(
                ['tenant_id' => $ugandaBranch->tenant_id, 'code' => $code],
                [
                    'name' => $name,
                    'description' => 'Seeded Phase 3A equipment classification.',
                    'default_meter_type' => $meter,
                    'default_capacity_unit' => $unit,
                    'fuel_efficiency_basis' => $basis,
                    'expected_fuel_efficiency' => $efficiency,
                    'fuel_tolerance_percent' => '15.0000',
                    'is_active' => true,
                    'created_by' => $director->id,
                    'updated_by' => $director->id,
                ],
            );
        }

        $locations = [
            'GUL-DEPOT' => EquipmentLocation::query()->updateOrCreate(
                ['tenant_id' => $ugandaBranch->tenant_id, 'code' => 'GUL-DEPOT'],
                ['branch_id' => $ugandaBranch->id, 'type' => 'depot', 'name' => 'Gulu Plant Depot', 'address' => 'Gulu Project Office', 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            ),
            'BUSUNJU' => EquipmentLocation::query()->updateOrCreate(
                ['tenant_id' => $ugandaBranch->tenant_id, 'code' => 'BUSUNJU-YARD'],
                ['branch_id' => $ugandaBranch->id, 'project_id' => $ugandaProject->id, 'site_id' => $busunjuSite->id, 'type' => 'site', 'name' => 'Busunju Site Yard', 'address' => $busunjuSite->location_name, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            ),
            'KIBOGA' => EquipmentLocation::query()->updateOrCreate(
                ['tenant_id' => $ugandaBranch->tenant_id, 'code' => 'KIBOGA-YARD'],
                ['branch_id' => $ugandaBranch->id, 'project_id' => $ugandaProject->id, 'site_id' => $kibogaSite->id, 'type' => 'site', 'name' => 'Kiboga-Hoima Site Yard', 'address' => $kibogaSite->location_name, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            ),
            'JUBA' => EquipmentLocation::query()->updateOrCreate(
                ['tenant_id' => $southSudanBranch->tenant_id, 'code' => 'JUBA-YARD'],
                ['branch_id' => $southSudanBranch->id, 'project_id' => $southSudanProject->id, 'site_id' => $jubaSite->id, 'type' => 'site', 'name' => 'Juba Access Works Yard', 'address' => $jubaSite->location_name, 'is_active' => true, 'created_by' => $director->id, 'updated_by' => $director->id],
            ),
        ];

        $assets = [
            ['EQ-GRD-001', 'Motor Grader', 'EARTHWORK', $ugandaBranch, $locations['BUSUNJU'], 'Caterpillar', '140K', 'CAT140K-001', 'owned', null, '12450.0000', 'available'],
            ['EQ-EXC-003', 'Hydraulic Excavator', 'EARTHWORK', $ugandaBranch, $locations['BUSUNJU'], 'Komatsu', 'PC300', 'KMTPC300-003', 'owned', null, '8320.5000', 'available'],
            ['EQ-RLR-002', 'Vibratory Roller', 'COMPACTION', $ugandaBranch, $locations['KIBOGA'], 'Bomag', 'BW211', 'BOM211-002', 'leased', null, '4680.0000', 'idle'],
            ['EQ-WTR-001', 'Water Bowser', 'HAULAGE', $ugandaBranch, $locations['KIBOGA'], 'Isuzu', 'FVZ', 'ISZFVZ-001', 'hired', $subcontractor, '186500.0000', 'available'],
            ['EQ-TIP-004', 'Tipper Truck', 'HAULAGE', $ugandaBranch, $locations['GUL-DEPOT'], 'Sinotruk', 'HOWO', 'HOWO-004', 'subcontractor', $subcontractor, '245000.0000', 'out_of_service'],
            ['EQ-GEN-001', 'Site Generator', 'SUPPORT', $southSudanBranch, $locations['JUBA'], 'Perkins', '1106A', 'PERK-1106A-001', 'owned', null, '2150.0000', 'available'],
        ];

        foreach ($assets as [$code, $name, $categoryCode, $branch, $location, $make, $model, $serial, $ownership, $owner, $reading, $status]) {
            $category = $categories[$categoryCode];
            $isOwned = $ownership === 'owned';
            $acquisitionAmount = $isOwned ? '450000000.0000' : null;
            $hireRate = $isOwned ? null : '850000.0000';
            $hireRateBasis = $isOwned ? null : 'day';

            $equipment = Equipment::query()->updateOrCreate(
                ['tenant_id' => $branch->tenant_id, 'asset_code' => $code],
                [
                    'branch_id' => $branch->id,
                    'equipment_category_id' => $category->id,
                    'name' => $name,
                    'make' => $make,
                    'model' => $model,
                    'serial_number' => $serial,
                    'ownership_type' => $ownership,
                    'owner_customer_id' => $owner?->id,
                    'owner_name' => $owner?->name,
                    'capacity_unit' => $category->default_capacity_unit,
                    'acquired_on' => '2024-01-15',
                    'acquisition_amount' => $acquisitionAmount,
                    'acquisition_currency_code' => $branch->default_currency_code,
                    'hire_rate' => $hireRate,
                    'hire_rate_basis' => $hireRateBasis,
                    'default_location_id' => $location->id,
                    'meter_type' => $category->default_meter_type,
                    'starting_meter_reading' => $reading,
                    'starting_meter_date' => '2026-07-01',
                    'fuel_efficiency_basis' => $category->fuel_efficiency_basis,
                    'expected_fuel_efficiency' => $category->expected_fuel_efficiency,
                    'fuel_tolerance_percent' => $category->fuel_tolerance_percent,
                    'current_status' => $status,
                    'current_location_id' => $location->id,
                    'current_meter_reading' => $reading,
                    'current_meter_read_at' => '2026-07-01 08:00:00',
                    'condition_summary' => $status === 'out_of_service' ? 'Awaiting mechanical inspection.' : 'Serviceable at register opening.',
                    'is_active' => true,
                    'created_by' => $director->id,
                    'updated_by' => $director->id,
                ],
            );
            [$meterUsage, $pendingCorrection] = $this->equipmentMeterSeedScenario($code);
            $this->seedEquipmentMeterHistory($director, $equipment, $meterUsage, $pendingCorrection);
        }

        $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();
        EquipmentAssignment::query()->updateOrCreate(
            ['equipment_id' => $grader->id, 'assigned_at' => '2026-07-05 07:30:00'],
            [
                'tenant_id' => $grader->tenant_id,
                'branch_id' => $ugandaBranch->id,
                'project_id' => $ugandaProject->id,
                'site_id' => $busunjuSite->id,
                'equipment_location_id' => $locations['BUSUNJU']->id,
                'custodian_staff_id' => $ugandaSiteEngineer->staff_id,
                'returned_at' => '2026-07-18 17:30:00',
                'handover_meter_reading' => '12450.0000',
                'return_meter_reading' => '12540.0000',
                'handover_condition' => 'Serviceable; tyres, blade and warning systems checked at handover.',
                'return_condition' => 'Returned serviceable with routine wear noted on the cutting edge.',
                'assignment_notes' => 'Busunju earthworks custody demonstration.',
                'return_notes' => 'Returned to the Busunju yard after the work section was completed.',
                'status' => EquipmentAssignment::STATUS_RETURNED,
                'handed_over_by' => $director->id,
                'received_by' => $ugandaSiteEngineer->id,
                'returned_by' => $ugandaSiteEngineer->id,
                'accepted_return_by' => $ugandaProjectManager->id,
                'return_location_id' => $locations['BUSUNJU']->id,
                'created_by' => $director->id,
                'updated_by' => $ugandaProjectManager->id,
            ],
        );

        $excavator = Equipment::query()->where('asset_code', 'EQ-EXC-003')->firstOrFail();
        EquipmentAssignment::query()->updateOrCreate(
            ['equipment_id' => $excavator->id, 'assigned_at' => '2026-07-21 07:00:00'],
            [
                'tenant_id' => $excavator->tenant_id,
                'branch_id' => $ugandaBranch->id,
                'project_id' => $ugandaProject->id,
                'site_id' => $busunjuSite->id,
                'equipment_location_id' => $locations['BUSUNJU']->id,
                'custodian_staff_id' => $ugandaSiteEngineer->staff_id,
                'expected_return_at' => '2026-09-30 17:00:00',
                'handover_meter_reading' => $excavator->current_meter_reading,
                'handover_condition' => 'Serviceable and accepted for drainage excavation works.',
                'assignment_notes' => 'Active custody example for assignment and return testing.',
                'status' => EquipmentAssignment::STATUS_ACTIVE,
                'handed_over_by' => $ugandaProjectManager->id,
                'received_by' => $ugandaSiteEngineer->id,
                'created_by' => $ugandaProjectManager->id,
                'updated_by' => $ugandaProjectManager->id,
            ],
        );
        $excavator->forceFill([
            'current_status' => 'assigned',
            'current_location_id' => $locations['BUSUNJU']->id,
            'current_project_id' => $ugandaProject->id,
            'current_site_id' => $busunjuSite->id,
            'current_custodian_id' => $ugandaSiteEngineer->staff_id,
            'condition_summary' => 'Serviceable and accepted for drainage excavation works.',
            'updated_by' => $ugandaProjectManager->id,
        ])->save();

        $bowser = Equipment::query()->where('asset_code', 'EQ-WTR-001')->firstOrFail();
        EquipmentTransfer::query()->updateOrCreate(
            ['equipment_id' => $bowser->id, 'requested_at' => '2026-08-01 09:00:00'],
            [
                'tenant_id' => $bowser->tenant_id,
                'source_branch_id' => $ugandaBranch->id,
                'source_location_id' => $locations['KIBOGA']->id,
                'source_project_id' => $ugandaProject->id,
                'source_site_id' => $kibogaSite->id,
                'destination_branch_id' => $ugandaBranch->id,
                'destination_location_id' => $locations['BUSUNJU']->id,
                'destination_project_id' => $ugandaProject->id,
                'destination_site_id' => $busunjuSite->id,
                'reason' => 'Busunju earthworks require additional dust suppression capacity.',
                'status' => EquipmentTransfer::STATUS_REQUESTED,
                'approved_at' => null,
                'dispatched_at' => null,
                'received_at' => null,
                'dispatch_meter_reading' => null,
                'receipt_meter_reading' => null,
                'dispatch_condition' => null,
                'receipt_condition' => null,
                'transport_reference' => null,
                'requested_by' => $ugandaProjectManager->id,
                'approved_by' => null,
                'dispatched_by' => null,
                'received_by' => null,
                'created_by' => $ugandaProjectManager->id,
                'updated_by' => $ugandaProjectManager->id,
            ],
        );

        EquipmentLocationConfirmation::query()->updateOrCreate(
            ['equipment_id' => $grader->id, 'observed_at' => '2026-07-20 08:15:00'],
            [
                'tenant_id' => $grader->tenant_id,
                'branch_id' => $ugandaBranch->id,
                'equipment_location_id' => $locations['BUSUNJU']->id,
                'project_id' => $ugandaProject->id,
                'site_id' => $busunjuSite->id,
                'observed_status' => 'available',
                'condition_observation' => 'Asset physically sighted serviceable in the Busunju yard.',
                'note' => 'Location confirmed during the morning plant inspection.',
                'confirmed_by' => $ugandaSiteEngineer->id,
                'created_by' => $ugandaSiteEngineer->id,
                'updated_by' => $ugandaSiteEngineer->id,
            ],
        );

        EquipmentFuelTransaction::query()->updateOrCreate(
            ['tenant_id' => $grader->tenant_id, 'voucher_reference' => 'FUEL-ISSUE-2407'],
            [
                'equipment_id' => $grader->id,
                'branch_id' => $ugandaBranch->id,
                'project_id' => $ugandaProject->id,
                'site_id' => $busunjuSite->id,
                'equipment_location_id' => $locations['BUSUNJU']->id,
                'transacted_at' => '2026-07-20 07:45:00',
                'transaction_type' => 'refuel',
                'fuel_type' => 'diesel',
                'quantity' => '180.0000',
                'unit' => 'litre',
                'source_type' => 'supplier',
                'provider_customer_id' => $subcontractor->id,
                'source_name' => $subcontractor->name,
                'unit_cost' => '5250.0000',
                'total_cost' => '945000.0000',
                'currency_code' => 'UGX',
                'meter_reading' => '12570.0000',
                'tank_level_before' => '35.0000',
                'tank_level_after' => '215.0000',
                'is_full_tank' => true,
                'issued_by_user_id' => $ugandaSiteEngineer->id,
                'received_by_staff_id' => $ugandaSiteEngineer->staff_id,
                'notes' => 'Morning refuel before Busunju grading operations.',
                'exception_status' => 'within_tolerance',
                'status' => EquipmentFuelTransaction::STATUS_POSTED,
                'submitted_by' => $ugandaSiteEngineer->id,
                'submitted_at' => '2026-07-20 07:50:00',
                'approved_by' => $ugandaProjectManager->id,
                'approved_at' => '2026-07-20 08:10:00',
                'posted_by' => $ugandaProjectManager->id,
                'posted_at' => '2026-07-20 08:10:00',
                'created_by' => $ugandaSiteEngineer->id,
                'updated_by' => $ugandaProjectManager->id,
            ],
        );

        EquipmentFuelTransaction::query()->updateOrCreate(
            ['tenant_id' => $excavator->tenant_id, 'voucher_reference' => 'FUEL-ISSUE-2608'],
            [
                'equipment_id' => $excavator->id,
                'branch_id' => $ugandaBranch->id,
                'project_id' => $ugandaProject->id,
                'site_id' => $busunjuSite->id,
                'equipment_location_id' => $locations['BUSUNJU']->id,
                'transacted_at' => '2026-08-15 07:30:00',
                'transaction_type' => 'issue',
                'fuel_type' => 'diesel',
                'quantity' => '220.0000',
                'unit' => 'litre',
                'source_type' => 'site_stock',
                'source_name' => 'Busunju site fuel store',
                'unit_cost' => '5300.0000',
                'total_cost' => '1166000.0000',
                'currency_code' => 'UGX',
                'meter_reading' => $excavator->current_meter_reading,
                'tank_level_before' => '40.0000',
                'tank_level_after' => '260.0000',
                'is_full_tank' => true,
                'issued_by_user_id' => $ugandaSiteEngineer->id,
                'received_by_staff_id' => $ugandaSiteEngineer->staff_id,
                'notes' => 'Pending manager review demonstration entry.',
                'exception_status' => 'not_evaluated',
                'status' => EquipmentFuelTransaction::STATUS_SUBMITTED,
                'submitted_by' => $ugandaSiteEngineer->id,
                'submitted_at' => '2026-08-15 07:35:00',
                'approved_by' => null,
                'approved_at' => null,
                'posted_by' => null,
                'posted_at' => null,
                'created_by' => $ugandaSiteEngineer->id,
                'updated_by' => $ugandaSiteEngineer->id,
            ],
        );

        $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();
        EquipmentFuelTransaction::query()->updateOrCreate(
            ['tenant_id' => $roller->tenant_id, 'voucher_reference' => 'FUEL-REVIEW-2608'],
            [
                'equipment_id' => $roller->id,
                'branch_id' => $ugandaBranch->id,
                'project_id' => $ugandaProject->id,
                'site_id' => $kibogaSite->id,
                'equipment_location_id' => $locations['KIBOGA']->id,
                'transacted_at' => '2026-08-14 06:50:00',
                'transaction_type' => 'refuel',
                'fuel_type' => 'diesel',
                'quantity' => '240.0000',
                'unit' => 'litre',
                'source_type' => 'mobile_bowser',
                'source_name' => 'Kiboga mobile bowser',
                'unit_cost' => '5300.0000',
                'total_cost' => '1272000.0000',
                'currency_code' => 'UGX',
                'meter_reading' => '4685.0000',
                'is_full_tank' => true,
                'issued_by_user_id' => $ugandaProjectManager->id,
                'received_by_staff_id' => $ugandaSiteEngineer->staff_id,
                'notes' => 'Exception demonstration requiring fleet review.',
                'exception_status' => 'review_required',
                'exception_reason' => 'Fuel quantity is above the expected range for the recorded meter movement.',
                'status' => EquipmentFuelTransaction::STATUS_POSTED,
                'submitted_by' => $ugandaSiteEngineer->id,
                'submitted_at' => '2026-08-14 07:00:00',
                'approved_by' => $ugandaProjectManager->id,
                'approved_at' => '2026-08-14 07:20:00',
                'posted_by' => $ugandaProjectManager->id,
                'posted_at' => '2026-08-14 07:20:00',
                'created_by' => $ugandaSiteEngineer->id,
                'updated_by' => $ugandaProjectManager->id,
            ],
        );

        $graderSchedule = EquipmentMaintenanceSchedule::query()->updateOrCreate(
            ['tenant_id' => $grader->tenant_id, 'equipment_id' => $grader->id, 'name' => '500-hour preventive service'],
            [
                'branch_id' => $ugandaBranch->id, 'maintenance_type' => 'preventive_service',
                'basis' => 'whichever_first', 'interval_days' => 90, 'interval_meter_units' => '500.0000',
                'last_service_date' => '2026-07-20', 'last_service_reading' => '12570.0000',
                'next_due_date' => '2026-10-18', 'next_due_reading' => '13070.0000',
                'warning_days' => 14, 'warning_meter_units' => '50.0000',
                'responsible_user_id' => $ugandaProjectManager->id, 'is_active' => true,
                'created_by' => $director->id, 'updated_by' => $ugandaProjectManager->id,
            ],
        );
        EquipmentMaintenanceSchedule::query()->updateOrCreate(
            ['tenant_id' => $roller->tenant_id, 'equipment_id' => $roller->id, 'name' => 'Monthly compactor inspection'],
            [
                'branch_id' => $ugandaBranch->id, 'maintenance_type' => 'inspection',
                'basis' => 'date', 'interval_days' => 30, 'last_service_date' => '2026-06-15',
                'next_due_date' => '2026-07-15', 'warning_days' => 7, 'warning_meter_units' => '0.0000',
                'responsible_user_id' => $ugandaProjectManager->id, 'is_active' => true,
                'created_by' => $director->id, 'updated_by' => $director->id,
            ],
        );
        $completedWorkOrder = EquipmentMaintenanceWorkOrder::query()->updateOrCreate(
            ['tenant_id' => $grader->tenant_id, 'reference' => 'MWO-GRD-0001'],
            [
                'equipment_id' => $grader->id, 'equipment_maintenance_schedule_id' => $graderSchedule->id,
                'branch_id' => $ugandaBranch->id, 'project_id' => $ugandaProject->id, 'site_id' => $busunjuSite->id,
                'equipment_location_id' => $locations['BUSUNJU']->id, 'maintenance_type' => 'preventive_service',
                'priority' => 'normal', 'description' => 'Scheduled engine oil, filter and general inspection service.',
                'status' => EquipmentMaintenanceWorkOrder::STATUS_COMPLETED, 'prior_equipment_status' => 'available',
                'reported_at' => '2026-07-19 10:00:00', 'planned_start_at' => '2026-07-20 09:00:00',
                'actual_start_at' => '2026-07-20 09:10:00', 'completed_at' => '2026-07-20 14:30:00',
                'opening_meter_reading' => '12568.0000', 'closing_meter_reading' => '12570.0000',
                'provider_customer_id' => $subcontractor->id, 'provider_name' => $subcontractor->name,
                'downtime_hours' => '5.3333', 'labour_cost' => '450000.0000', 'parts_cost' => '780000.0000',
                'other_cost' => '70000.0000', 'total_cost' => '1300000.0000', 'currency_code' => 'UGX',
                'findings' => 'Engine oil degraded; primary fuel filter due for replacement.',
                'work_performed' => 'Changed engine oil and filters, greased joints and completed safety inspection.',
                'completion_notes' => 'Returned serviceable.', 'next_service_date' => '2026-10-18',
                'next_service_reading' => '13070.0000', 'requested_by' => $ugandaProjectManager->id,
                'approved_by' => $director->id, 'supervised_by' => $ugandaProjectManager->id,
                'completed_by' => $ugandaProjectManager->id, 'created_by' => $ugandaProjectManager->id,
                'updated_by' => $ugandaProjectManager->id,
            ],
        );
        EquipmentMaintenancePartLine::query()->updateOrCreate(
            ['equipment_maintenance_work_order_id' => $completedWorkOrder->id, 'part_code' => 'FLT-OIL-140K'],
            [
                'tenant_id' => $grader->tenant_id, 'part_name' => 'Engine oil filter',
                'quantity' => '2.0000', 'unit' => 'piece', 'unit_cost' => '390000.0000',
                'total_cost' => '780000.0000', 'currency_code' => 'UGX',
                'provider_customer_id' => $subcontractor->id, 'provider_name' => $subcontractor->name,
                'reference' => 'SRV-INV-2407', 'notes' => 'Historical snapshot; inventory posting begins in Phase 3B.',
            ],
        );
        $this->seedDocument(
            actor: $director,
            typeCode: 'INSPECTION_RECORD',
            branch: $ugandaBranch,
            title: 'Motor grader preventive service completion record',
            reference: 'EQ-GRD-001-SERVICE-20260720',
            content: 'Signed service record covering oil, filters, lubrication and safety inspection.',
            links: [[$completedWorkOrder::class, $completedWorkOrder->id], [$grader::class, $grader->id]],
            documentNumber: 'SRV-INV-2407',
            revision: 'Completed',
            discipline: 'Fleet Maintenance',
            issuer: $subcontractor->name,
        );
        $this->seedDocument(
            actor: $director,
            typeCode: 'PERMIT',
            branch: $ugandaBranch,
            title: 'Motor grader insurance and road-use certificate',
            reference: 'EQ-GRD-001-INSURANCE-2026',
            content: 'Demo insurance and compliance certificate for fleet document expiry monitoring.',
            links: [[$grader::class, $grader->id]],
            expiresOn: '2026-09-30',
            documentNumber: 'INS-EQ-GRD-001-2026',
            revision: 'Issued',
            discipline: 'Fleet Compliance',
            issuer: 'Demo General Insurance',
        );
        EquipmentMaintenanceWorkOrder::query()->updateOrCreate(
            ['tenant_id' => $excavator->tenant_id, 'reference' => 'MWO-EXC-0002'],
            [
                'equipment_id' => $excavator->id, 'branch_id' => $ugandaBranch->id,
                'project_id' => $ugandaProject->id, 'site_id' => $busunjuSite->id,
                'equipment_location_id' => $locations['BUSUNJU']->id, 'maintenance_type' => 'inspection',
                'priority' => 'high', 'description' => 'Inspect hydraulic hose seepage reported during daily checks.',
                'status' => EquipmentMaintenanceWorkOrder::STATUS_PLANNED,
                'reported_at' => '2026-08-15 16:20:00', 'planned_start_at' => '2026-08-17 08:00:00',
                'opening_meter_reading' => $excavator->current_meter_reading,
                'requested_by' => $ugandaSiteEngineer->id, 'created_by' => $ugandaSiteEngineer->id,
                'updated_by' => $ugandaSiteEngineer->id,
            ],
        );

        $approvedFleetReport = DailySiteReport::query()
            ->where('reference', 'DSR-BUSUNJU-20241206')
            ->first();
        $approvedFleetLine = $approvedFleetReport?->equipmentLines()->first();

        if ($approvedFleetReport instanceof DailySiteReport && $approvedFleetLine instanceof DailySiteReportEquipmentLine) {
            $approvedFleetLine->forceFill([
                'equipment_id' => $excavator->id,
                'equipment_name' => $excavator->name,
                'equipment_identifier' => $excavator->asset_code,
                'fuel_transaction_type' => 'consumption',
                'evidence_note' => 'Seeded signed plant and fuel sheet for correction-workflow demonstration.',
                'fleet_posting_status' => 'unposted',
                'fleet_posted_at' => null,
            ])->save();

            resolve(PostApprovedDsrEquipmentLines::class)->handle($approvedFleetReport, $director);
        }
    }

    /** @return array{0: string|null, 1: bool} */
    private function equipmentMeterSeedScenario(string $assetCode): array
    {
        return match ($assetCode) {
            'EQ-GRD-001' => ['120.0000', false],
            'EQ-EXC-003' => ['80.0000', true],
            default => [null, false],
        };
    }

    private function seedEquipmentMeterHistory(User $actor, Equipment $equipment, ?string $usage, bool $pendingCorrection): void
    {
        $opening = EquipmentMeterReading::query()->updateOrCreate(
            ['equipment_id' => $equipment->id, 'event_type' => 'opening', 'corrects_reading_id' => null],
            [
                'tenant_id' => $equipment->tenant_id,
                'branch_id' => $equipment->branch_id,
                'equipment_location_id' => $equipment->default_location_id,
                'reading_value' => $equipment->starting_meter_reading,
                'read_at' => $equipment->starting_meter_date,
                'previous_reading' => null,
                'usage' => null,
                'status' => EquipmentMeterReading::STATUS_ACCEPTED,
                'recorded_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => $equipment->starting_meter_date,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        if ($usage === null) {
            return;
        }

        $value = number_format((float) $opening->reading_value + (float) $usage, 4, '.', '');
        $latest = EquipmentMeterReading::query()->updateOrCreate(
            ['equipment_id' => $equipment->id, 'event_type' => 'manual', 'read_at' => '2026-07-20 08:00:00'],
            [
                'tenant_id' => $equipment->tenant_id,
                'branch_id' => $equipment->branch_id,
                'equipment_location_id' => $equipment->default_location_id,
                'reading_value' => $value,
                'previous_reading' => $opening->reading_value,
                'usage' => $usage,
                'status' => EquipmentMeterReading::STATUS_ACCEPTED,
                'evidence_note' => 'Seeded verified meter observation.',
                'recorded_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => '2026-07-20 08:00:00',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
        $equipment->forceFill(['current_meter_reading' => $latest->reading_value, 'current_meter_read_at' => $latest->read_at])->save();

        if (! $pendingCorrection) {
            return;
        }

        EquipmentMeterReading::query()->updateOrCreate(
            ['equipment_id' => $equipment->id, 'event_type' => 'correction', 'corrects_reading_id' => $latest->id],
            [
                'tenant_id' => $equipment->tenant_id,
                'branch_id' => $equipment->branch_id,
                'equipment_location_id' => $equipment->default_location_id,
                'reading_value' => number_format((float) $latest->reading_value - 5, 4, '.', ''),
                'read_at' => $latest->read_at,
                'status' => EquipmentMeterReading::STATUS_PENDING,
                'reason' => 'Transcription error found during weekly logbook reconciliation.',
                'evidence_note' => 'Physical hour-meter photograph and signed operator log available.',
                'recorded_by' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }

    private function seedOperationalControls(
        User $director,
        User $projectManager,
        User $siteEngineer,
        Project $project,
        Site $busunjuSite,
        Site $kibogaSite,
    ): void {
        $tenantCalendar = ReportingCalendar::query()->updateOrCreate(
            [
                'tenant_id' => $project->tenant_id,
                'project_id' => null,
                'site_id' => null,
                'name' => 'Point Investment standard reporting week',
            ],
            [
                'branch_id' => null,
                'timezone' => 'Africa/Kampala',
                'reporting_deadline' => '18:00:00',
                'working_days' => [1, 2, 3, 4, 5, 6],
                'missing_escalation_days' => 2,
                'is_active' => true,
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );

        $exceptionDate = now()->addMonth()->startOfMonth()->toDateString();
        $calendarException = ReportingCalendarException::query()
            ->where('reporting_calendar_id', $tenantCalendar->id)
            ->whereDate('exception_date', $exceptionDate)
            ->first() ?? new ReportingCalendarException();
        $calendarException->fill([
            'tenant_id' => $project->tenant_id,
            'branch_id' => null,
            'reporting_calendar_id' => $tenantCalendar->id,
            'exception_date' => $exceptionDate,
            'type' => ReportingCalendarException::TYPE_NON_WORKING,
            'name' => 'Planned company shutdown',
            'reason' => 'Demo dated exception for reporting calendar training.',
            'created_by' => $calendarException->exists ? $calendarException->created_by : $director->id,
            'updated_by' => $director->id,
        ])->save();

        ReportingCalendar::query()->updateOrCreate(
            [
                'tenant_id' => $project->tenant_id,
                'site_id' => $busunjuSite->id,
                'name' => 'Busunju early reporting deadline',
            ],
            [
                'branch_id' => $busunjuSite->branch_id,
                'project_id' => $project->id,
                'timezone' => 'Africa/Kampala',
                'reporting_deadline' => '17:30:00',
                'working_days' => [1, 2, 3, 4, 5, 6],
                'missing_escalation_days' => 2,
                'is_active' => true,
                'created_by' => $director->id,
                'updated_by' => $director->id,
            ],
        );

        $obligations = [
            [$busunjuSite, now()->subDay(), ExpectedDailySiteReport::STATUS_MISSING, $siteEngineer],
            [$busunjuSite, now(), ExpectedDailySiteReport::STATUS_EXPECTED, $siteEngineer],
            [$kibogaSite, now()->subDay(), ExpectedDailySiteReport::STATUS_MISSING, $projectManager],
        ];

        foreach ($obligations as [$site, $date, $status, $owner]) {
            $this->seedExpectedReport(
                project: $project,
                site: $site,
                reportDate: $date,
                values: [
                    'deadline_at' => $date->copy()->setTime(18, 0),
                    'status' => $status,
                    'notified_at' => $status === ExpectedDailySiteReport::STATUS_MISSING ? now() : null,
                    'escalated_at' => null,
                ],
            );

            $seedKey = 'phase-2d-'.$site->id.'-'.$date->toDateString();

            if ($status === ExpectedDailySiteReport::STATUS_MISSING
                && $owner->notifications()->where('data->seed_key', $seedKey)->doesntExist()) {
                $owner->notify(new OperationalNotification([
                    'tenant_id' => $project->tenant_id,
                    'branch_id' => $site->branch_id,
                    'category' => 'daily_site_reports',
                    'severity' => 'warning',
                    'title' => 'Daily site report is overdue',
                    'message' => $site->name.' has not submitted its report for '.$date->toDateString().'.',
                    'action_url' => '/operations-dashboard',
                    'seed_key' => $seedKey,
                ]));
            }
        }
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
            ['EXPENSE_RECEIPT', 'Expense receipt', false, true],
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
            $site = $report->site_id === $primarySite->id ? $primarySite : $secondarySite;
            $this->seedExpectedReport(
                project: $project,
                site: $site,
                reportDate: $report->report_date,
                values: [
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

        $this->seedExpectedReport(
            project: $project,
            site: $secondarySite,
            reportDate: now()->addDay(),
            values: [
                'deadline_at' => now()->addDay()->setTime(18, 0),
                'status' => ExpectedDailySiteReport::STATUS_EXPECTED,
                'daily_site_report_id' => null,
                'submitted_at' => null,
                'notified_at' => null,
                'escalated_at' => null,
            ],
        );
    }

    /** @param array<string, mixed> $values */
    private function seedExpectedReport(Project $project, Site $site, CarbonInterface $reportDate, array $values): ExpectedDailySiteReport
    {
        $date = $reportDate->toDateString();
        $expected = ExpectedDailySiteReport::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('site_id', $site->id)
            ->whereDate('report_date', $date)
            ->first() ?? new ExpectedDailySiteReport();

        $expected->fill([
            'tenant_id' => $project->tenant_id,
            'branch_id' => $site->branch_id,
            'project_id' => $project->id,
            'site_id' => $site->id,
            'report_date' => $date,
            ...$values,
        ])->save();

        return $expected;
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
