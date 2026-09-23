<?php

declare(strict_types=1);

use App\Enums\ExpenseStatus;
use App\Models\Branch;
use App\Models\DailySiteReport;
use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\ExpensePayment;
use App\Models\InventoryPriceTier;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00:00 UTC'));
    $this->dashboardUser = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    resolve(TenantContext::class)->set($this->dashboardUser->tenant);
    $this->dashboardUser->syncRoles([]);
    $this->dashboardUser->syncPermissions(['pos.view', 'pos.view-payments', 'pos.view-all-sales', 'expenses.view', 'expenses.view-costs', 'expense-payments.view', 'branches.view-all', 'projects.view', 'projects.view-all']);

    $this->dashboardBranch = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail()->branch_id;
    ExpensePayment::query()->delete();
    Expense::query()->update(['status' => ExpenseStatus::Cancelled]);
    $this->actingAs($this->dashboardUser)->withSession(['current_branch_id' => $this->dashboardBranch, 'current_branch_all' => false]);
    $this->withoutVite();
});

function dashboardSale(User $user, array $attributes = []): PosSale
{
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();

    return PosSale::factory()->create([
        'tenant_id' => $user->tenant_id, 'branch_id' => $store->branch_id, 'inventory_store_id' => $store->id,
        'inventory_price_tier_id' => InventoryPriceTier::query()->where('code', 'RETAIL')->firstOrFail()->id,
        'sale_number' => fake()->unique()->bothify('SALE-########'), 'checkout_key' => fake()->uuid(),
        'status' => 'completed', 'currency_code' => 'UGX', 'subtotal' => '1000', 'total_amount' => '1000',
        'amount_paid' => '0', 'balance_due' => '1000', 'payment_status' => 'unpaid',
        'sold_by' => $user->id, 'completed_by' => $user->id, 'completed_at' => now(), ...$attributes,
    ]);
}

function dashboardReceipt(PosSale $sale, array $attributes = []): PosPayment
{
    return PosPayment::factory()->create([
        'tenant_id' => $sale->tenant_id, 'branch_id' => $sale->branch_id, 'pos_sale_id' => $sale->id,
        'payment_number' => fake()->unique()->bothify('RECEIPT-########'), 'method' => 'cash',
        'amount' => '200', 'currency_code' => $sale->currency_code, 'status' => 'recorded',
        'recorded_by' => $sale->sold_by, 'recorded_at' => now(), ...$attributes,
    ]);
}

it('shows no financial data without permissions', function (): void {
    $this->dashboardUser->syncPermissions([]);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('dashboard')->has('cards', 0)->missing('cashFlow')->missing('sections')->missing('currentUser')->missing('generatedAt'));
});

it('supports direct and role permissions while omitting restricted payments', function (): void {
    $this->dashboardUser->syncPermissions(['pos.view']);
    dashboardSale($this->dashboardUser);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('cards', 2)->where('cards.0.id', 'sales')->where('cards.1.id', 'receivables')->missing('cashFlow'));
    $role = Role::factory()->create(['name' => 'Dashboard receipts', 'guard_name' => 'web']);
    $role->givePermissionTo('pos.view-payments');

    $this->dashboardUser->assignRole($role);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('cashFlow.received')->missing('cashFlow.paid')->missing('cashFlow.net'));
});

it('requires expense amount and payment permissions independently', function (): void {
    $this->dashboardUser->syncPermissions(['expenses.view', 'expense-payments.view']);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page->has('cards', 0)->missing('cashFlow'));
    $this->dashboardUser->syncPermissions(['expenses.view', 'expenses.view-costs']);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('cards', 1)->where('cards.0.id', 'expenses')->missing('cashFlow'));
});

it('separates period sales from receipts and current customer debts', function (): void {
    $old = dashboardSale($this->dashboardUser, ['completed_at' => now()->subMonths(2), 'balance_due' => '800']);
    dashboardReceipt($old, ['amount' => '200']);
    dashboardSale($this->dashboardUser, ['total_amount' => '300', 'balance_due' => '300']);
    dashboardSale($this->dashboardUser, ['status' => 'draft', 'total_amount' => '9000']);
    dashboardSale($this->dashboardUser, ['status' => 'cancelled', 'total_amount' => '9000']);
    $this->get(route('dashboard', ['period' => 'today']))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('cards.0.amount', '300.0000')->where('cards.2.amount', '1100.0000')
        ->where('cards.2.snapshot', true)->where('cashFlow.received.amount', '200.0000')->where('cashFlow.received.count', 1));
});

it('counts upfront payments but only approved expenses and all-time unpaid balances', function (): void {
    $approved = Expense::factory()->create(['branch_id' => $this->dashboardBranch, 'status' => 'approved', 'expense_date' => now()->subMonths(2), 'total_amount' => '1000']);
    ExpensePayment::factory()->create(['expense_id' => $approved->id, 'branch_id' => $this->dashboardBranch, 'amount' => '300', 'paid_at' => now()->subMonth()]);
    ExpensePayment::factory()->create(['expense_id' => $approved->id, 'branch_id' => $this->dashboardBranch, 'amount' => '100', 'payment_method' => 'bank']);
    ExpensePayment::factory()->create(['expense_id' => $approved->id, 'branch_id' => $this->dashboardBranch, 'amount' => '50', 'status' => 'reversed']);
    $draft = Expense::factory()->create(['branch_id' => $this->dashboardBranch, 'status' => 'draft']);
    ExpensePayment::factory()->create(['expense_id' => $draft->id, 'branch_id' => $this->dashboardBranch, 'amount' => '25']);
    Expense::factory()->create(['branch_id' => $this->dashboardBranch, 'status' => 'approved', 'total_amount' => '200']);
    $this->get(route('dashboard', ['period' => 'today']))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('cards.1.amount', '200.0000')->where('cards.3.amount', '800.0000')
        ->where('cashFlow.paid.amount', '125.0000')->where('cashFlow.paid.count', 2)->where('cashFlow.net', '-125.0000'));
});

it('keeps currencies separate and excludes reversed receipts', function (): void {
    $ugx = dashboardSale($this->dashboardUser);
    dashboardReceipt($ugx, ['amount' => '100']);
    dashboardReceipt($ugx, ['amount' => '500', 'status' => 'reversed']);
    $usd = dashboardSale($this->dashboardUser, ['currency_code' => 'USD', 'total_amount' => '10', 'balance_due' => '7']);
    dashboardReceipt($usd, ['amount' => '3']);
    $this->get(route('dashboard', ['currency' => 'USD']))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('currencies', 2)->where('cards.0.amount', '10.0000')->where('cashFlow.received.amount', '3.0000')->where('cashFlow.received.count', 1));
    $this->get(route('dashboard', ['currency' => 'UGX']))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('cashFlow.received.amount', '100.0000')->where('cashFlow.methods.0.receivedCount', 1));
});

it('uses tenant-local dates and includes the complete end date', function (): void {
    $sale = dashboardSale($this->dashboardUser);
    dashboardReceipt($sale, ['recorded_at' => '2026-09-20 21:30:00', 'amount' => '10']);
    dashboardReceipt($sale, ['recorded_at' => '2026-09-21 20:59:59', 'amount' => '20']);
    dashboardReceipt($sale, ['recorded_at' => '2026-09-21 21:00:00', 'amount' => '40']);
    $this->get(route('dashboard', ['period' => 'range', 'from' => '2026-09-21', 'to' => '2026-09-21']))->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('cashFlow.received.amount', '30.0000')->where('cashFlow.series.0.received', '30.0000'));
});

it('validates date filters and selected currencies', function (): void {
    foreach ([
        ['period' => 'unknown'], ['period' => 'range'],
        ['period' => 'range', 'from' => '2026-09-21', 'to' => '2026-09-20'],
        ['period' => 'range', 'from' => '2026-02-30', 'to' => '2026-09-20'],
        ['period' => 'range', 'from' => '2024-01-01', 'to' => '2026-09-20'],
        ['period' => 'range', 'from' => '2026-09-21', 'to' => '2026-09-22'],
        ['currency' => 'XYZ'],
    ] as $query) {
        $this->getJson(route('dashboard', $query))->assertUnprocessable();
    }
});

it('scopes sales to the selected branch and permitted seller', function (): void {
    $this->dashboardUser->revokePermissionTo('pos.view-all-sales');
    dashboardSale($this->dashboardUser, ['total_amount' => '100']);
    $another = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    dashboardSale($another, ['total_amount' => '900']);
    $otherStore = InventoryStore::query()->firstOrFail()->replicate();
    $otherStore->branch_id = Branch::query()->create([
        'tenant_id' => $this->dashboardUser->tenant_id, 'name' => 'Other branch', 'code' => 'DASHBOARD-OTHER',
        'country_code' => 'UG', 'default_currency_code' => 'UGX', 'status' => 'active',
    ])->id;
    $otherStore->code = 'DASHBOARD-OTHER';
    $otherStore->save();
    dashboardSale($this->dashboardUser, ['branch_id' => $otherStore->branch_id, 'inventory_store_id' => $otherStore->id, 'total_amount' => '700']);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('cards.0.amount', '100.0000'));
});

it('excludes expenses and payments attached to inaccessible projects', function (): void {
    $this->dashboardUser->revokePermissionTo(['projects.view', 'projects.view-all']);
    $project = Project::query()->firstOrFail();
    $expense = Expense::factory()->create(['branch_id' => $this->dashboardBranch, 'status' => 'approved', 'total_amount' => '800']);
    $line = ExpenseLine::query()->firstOrFail()->replicate();
    $line->expense_id = $expense->id;
    $line->project_id = $project->id;
    $line->save();
    ExpensePayment::factory()->create(['branch_id' => $this->dashboardBranch, 'expense_id' => $expense->id, 'amount' => '500']);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('cards.1.amount', '0.0000')->where('cards.3.amount', '0.0000')->where('cashFlow.paid.amount', '0.0000'));
});

it('resolves presets and returns empty chart intervals instead of fabricated activity', function (string $period, string $from): void {
    $this->get(route('dashboard', ['period' => $period]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('filters.from', $from)->where('filters.to', '2026-09-21')->where('cashFlow.received.amount', '0.0000')
        ->where('cashFlow.paid.amount', '0.0000')->has('cashFlow.methods', 0));
})->with([['today', '2026-09-21'], ['last7', '2026-09-15'], ['month', '2026-09-01'], ['quarter', '2026-07-01']]);

it('shows operational cards without financial access and keeps current snapshots outside the date filter', function (): void {
    $this->dashboardUser->syncPermissions(['dashboards.projects.view', 'projects.view', 'projects.view-all', 'branches.view-all']);
    $count = Project::query()->where('branch_id', $this->dashboardBranch)->where('status', 'active')->count();
    foreach (['today', 'quarter'] as $period) {
        $this->get(route('dashboard', ['period' => $period]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->has('cards', 0)->missing('cashFlow')->where('operationalCards.0.id', 'projects')->where('operationalCards.0.count', $count)->missing('lowStock'));
    }
});

it('exposes inventory alert counts only with stock permission', function (): void {
    $this->dashboardUser->syncPermissions(['inventory.stock.view', 'branches.view-all']);
    $setting = InventoryStoreItem::query()->whereHas('store', fn ($query) => $query->where('branch_id', $this->dashboardBranch))->firstOrFail();
    $setting->update(['is_active' => true, 'minimum_stock' => '999999']);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('cards', 0)->missing('cashFlow')->where('operationalCards.0.id', 'low-stock')
        ->has('operationalCards.0.nearExpiry')->has('operationalCards.0.expired')->missing('lowStock'));
    $this->dashboardUser->syncPermissions([]);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page->has('operationalCards', 0)->missing('lowStock'));
});

it('guards actionable DSR approval counts independently from financial cards', function (): void {
    $reports = DailySiteReport::query()->where('branch_id', $this->dashboardBranch);
    $report = (clone $reports)->firstOrFail();
    (clone $reports)->update(['status' => DailySiteReport::STATUS_APPROVED]);
    $report->update(['status' => DailySiteReport::STATUS_REVIEWED]);
    $this->dashboardUser->syncPermissions(['daily-site-reports.view-dashboard', 'daily-site-reports.approve', 'projects.view-all', 'branches.view-all', 'inventory.dsr-material-usage.post']);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('cards', 0)->missing('cashFlow')->where('operationalCards.0.id', 'dsr-approval')->where('operationalCards.0.count', 1));
    $this->dashboardUser->syncPermissions(['daily-site-reports.view', 'branches.view-all']);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page->has('operationalCards', 0));
});

it('shows work queues using the corresponding operational permissions', function (array $permissions, array $ids): void {
    $this->dashboardUser->syncPermissions($permissions);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('cards', 0)->missing('cashFlow')
        ->where('workQueues', fn ($queues): bool => collect($queues)->pluck('id')->all() === $ids));
    $this->dashboardUser->syncPermissions([]);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page->has('workQueues', 0));
})->with([
    'site reporting' => [['daily-site-reports.create'], ['dsr-drafts', 'dsr-returned']],
    'store requests' => [['inventory.requisitions.view'], ['requisitions-issue']],
    'procurement' => [['inventory.purchase-orders.view'], ['orders-open']],
    'fleet' => [['equipment.view', 'equipment.dashboard.view'], ['equipment-unavailable', 'maintenance-open']],
]);


it('counts stocked expiry dates at the thirty day boundary', function (): void {
    $this->dashboardUser->syncPermissions(['inventory.stock.view', 'branches.view-all']);
    $batch = \App\Models\InventoryBatch::query()->whereHas('store', fn ($query) => $query->where('branch_id', $this->dashboardBranch))->firstOrFail();
    \App\Models\InventoryBatch::query()->update(['expires_on' => null]);
    foreach ([[-1, 0, 1], [0, 1, 0], [30, 1, 0], [31, 0, 0]] as [$days, $near, $expired]) {
        $batch->update(['expires_on' => now()->addDays($days)->toDateString()]);
        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('operationalCards.0.nearExpiry', $near)->where('operationalCards.0.expired', $expired)->missing('lowStock'));
    }
});

it('shows current paid and unpaid counts for approved period expenses', function (): void {
    $paid = Expense::factory()->create(['branch_id' => $this->dashboardBranch, 'status' => 'approved', 'currency_code' => 'UGX', 'expense_date' => now(), 'total_amount' => '100']);
    $partial = Expense::factory()->create(['branch_id' => $this->dashboardBranch, 'status' => 'approved', 'currency_code' => 'UGX', 'expense_date' => now(), 'total_amount' => '100']);
    ExpensePayment::factory()->create(['expense_id' => $paid->id, 'branch_id' => $this->dashboardBranch, 'amount' => '100', 'status' => 'recorded']);
    ExpensePayment::factory()->create(['expense_id' => $partial->id, 'branch_id' => $this->dashboardBranch, 'amount' => '25', 'status' => 'recorded']);
    Expense::factory()->create(['branch_id' => $this->dashboardBranch, 'status' => 'draft', 'currency_code' => 'UGX', 'expense_date' => now()]);
    $this->get(route('dashboard', ['period' => 'today', 'currency' => 'UGX']))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('cards.1.subtitle', '1 paid · 1 unpaid')->where('cards.3.title', 'Unpaid expenses'));
    $this->dashboardUser->revokePermissionTo('expense-payments.view');
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('cards.1.subtitle', null));
});

it('limits equipment summaries to the three highest priority nonzero statuses', function (): void {
    $this->dashboardUser->syncPermissions(['equipment.view', 'equipment.dashboard.view', 'branches.view-all']);
    $template = \App\Models\Equipment::query()->where('branch_id', $this->dashboardBranch)->firstOrFail();
    \App\Models\Equipment::query()->where('branch_id', $this->dashboardBranch)->update(['is_active' => false]);
    foreach (['available', 'assigned', 'under_maintenance', 'out_of_service'] as $status) {
        $equipment = $template->replicate();
        $equipment->fill([
            'asset_code' => 'DASH-'.strtoupper($status), 'serial_number' => null,
            'registration_number' => null, 'chassis_number' => null,
            'is_active' => true, 'current_status' => $status,
        ]);
        $equipment->save();
    }
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('operationalCards.0.id', 'equipment')->where('operationalCards.0.count', 4)
        ->where('operationalCards.0.subtitle', '1 out of service · 1 maintenance · 1 assigned'));
    \App\Models\Equipment::query()->where('branch_id', $this->dashboardBranch)->where('current_status', 'out_of_service')->update(['is_active' => false]);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('operationalCards.0.count', 3)->where('operationalCards.0.subtitle', '1 maintenance · 1 assigned · 1 available'));
});
