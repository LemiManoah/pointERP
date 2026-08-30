<?php

declare(strict_types=1);

use App\Enums\ExpensePaymentStatus;
use App\Enums\ExpenseStatus;
use App\Models\DailySiteReport;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\ExpensePayment;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectPerformanceSummary;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('shows seeded expenses, payments, categories and items to an authorised user', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    $this->actingAs($director)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/expenses/index')
            ->has('expenses', 3)
            ->has('payments', 1)
            ->has('categories', 5)
            ->has('expenseItems', 9));
});

it('enforces branch access even when the user has general expense permission', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $kampalaExpense = Expense::query()->where('expense_number', 'EXP-DEMO-001')->firstOrFail();

    $this->actingAs($siteManager)->get(route('expenses.show', $kampalaExpense))->assertForbidden();
});

it('allows permission-based self approval and keeps approved expenses immutable', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $item = ExpenseItem::query()->where('code', 'DRINKING-WATER')->firstOrFail();

    $this->actingAs($manager)->post(route('expenses.store'), [
        'branch_id' => $project->branch_id,
        'expense_date' => now()->toDateString(),
        'payee_type' => 'other',
        'payee_name' => 'Busunju water delivery team',
        'currency_code' => 'UGX',
        'description' => 'Drinking water for the field crew.',
        'lines' => [[
            'expense_item_id' => $item->id,
            'project_id' => $project->id,
            'quantity' => '10',
            'unit_amount' => '5000',
        ]],
    ])->assertRedirect();

    $expense = Expense::query()->where('payee_name_snapshot', 'Busunju water delivery team')->firstOrFail();
    $this->actingAs($manager)->post(route('expenses.submit', $expense))->assertRedirect();
    $this->actingAs($manager)->post(route('expenses.approve', $expense))->assertRedirect();
    $this->actingAs($manager)->put(route('expenses.update', $expense), [])->assertForbidden();

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Approved)
        ->and($expense->getAttribute('approved_by'))->toBe($manager->id);
});

it('normalizes fixed expenses and requires quantity for quantified items', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $branchId = Expense::query()->where('expense_number', 'EXP-DEMO-001')->value('branch_id');
    $fixedItem = ExpenseItem::query()->where('code', 'YAKA')->firstOrFail();
    $quantifiedItem = ExpenseItem::query()->where('code', 'SITE-MEALS')->firstOrFail();

    $this->actingAs($director)->post(route('expenses.store'), [
        'branch_id' => $branchId,
        'expense_date' => now()->toDateString(),
        'payee_type' => 'other',
        'payee_name' => 'Fixed charge test provider',
        'currency_code' => 'UGX',
        'lines' => [[
            'expense_item_id' => $fixedItem->id,
            'quantity' => '99',
            'unit_amount' => '250000',
        ]],
    ])->assertRedirect();

    $fixedLine = Expense::query()
        ->where('payee_name_snapshot', 'Fixed charge test provider')
        ->firstOrFail()
        ->lines()
        ->firstOrFail();

    expect($fixedLine->has_quantity_snapshot)->toBeFalse()
        ->and($fixedLine->quantity)->toBe('1.0000')
        ->and($fixedLine->amount)->toBe('250000.0000');

    $this->actingAs($director)->post(route('expenses.store'), [
        'branch_id' => $branchId,
        'expense_date' => now()->toDateString(),
        'payee_type' => 'other',
        'payee_name' => 'Quantified charge test provider',
        'currency_code' => 'UGX',
        'lines' => [[
            'expense_item_id' => $quantifiedItem->id,
            'unit_amount' => '15000',
        ]],
    ])->assertSessionHasErrors('lines.0.quantity');
});

it('omits expense amounts for users without cost visibility', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $expense = Expense::query()->where('expense_number', 'EXP-DEMO-002')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('expenses.show', $expense))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('can.viewCosts', false)
            ->where('expense.total_amount', null)
            ->where('expense.lines.0.unit_amount', null)
            ->where('expense.lines.0.amount', null));
});

it('requires evidence before an evidence-controlled draft can be submitted', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $draft = Expense::query()->where('expense_number', 'EXP-DEMO-003')->firstOrFail();

    $this->actingAs($director)
        ->post(route('expenses.submit', $draft))
        ->assertSessionHasErrors('expense');

    expect($draft->refresh()->status)->toBe(ExpenseStatus::Draft);
});

it('records partial payments, blocks overpayment and preserves reversals', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $expense = Expense::query()->where('expense_number', 'EXP-DEMO-001')->firstOrFail();

    $this->actingAs($director)->post(route('expenses.payments.store', $expense), [
        'paid_at' => now()->toDateTimeLocalString(),
        'amount' => '100000',
        'payment_method' => 'bank',
        'reference' => 'BANK-TEST-001',
    ])->assertRedirect(route('expenses.show', $expense));

    $this->actingAs($director)->post(route('expenses.payments.store', $expense), [
        'paid_at' => now()->toDateTimeLocalString(),
        'amount' => '500000',
        'payment_method' => 'bank',
    ])->assertSessionHasErrors('amount');

    $payment = ExpensePayment::query()->where('reference', 'BANK-TEST-001')->firstOrFail();
    $this->actingAs($director)->post(route('expense-payments.reverse', $payment), [
        'reason' => 'The bank reference was entered against the wrong transaction.',
    ])->assertRedirect(route('expenses.show', $expense));

    expect($payment->refresh()->status)->toBe(ExpensePaymentStatus::Reversed)
        ->and($payment->reversal_reason)->not->toBeNull()
        ->and($expense->refresh()->paidAmount())->toBe(200000.0);
});

it('links an approved DSR expense directly and includes it once in project costs', function (): void {
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $expense = Expense::query()->where('expense_number', 'EXP-DEMO-002')->firstOrFail();
    $summary = resolve(ProjectPerformanceSummary::class)->forProject($project, true);
    assert(is_array($summary));
    $approvedDsrInput = (float) DailySiteReport::query()
        ->where('project_id', $project->id)
        ->where('status', DailySiteReport::STATUS_APPROVED)
        ->sum('input_cost');

    expect($expense->daily_site_report_id)->not->toBeNull()
        ->and($expense->lines()->firstOrFail()->amount)->toBe('450000.0000')
        ->and($summary['totals']['operational_expenses'])->toBe('450000.0000')
        ->and($summary['totals']['actual_input_cost'])->toBe(number_format($approvedDsrInput + 450000, 4, '.', ''));
});

it('creates a permission-guarded expense draft directly from an editable DSR', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $report = DailySiteReport::query()->where('status', DailySiteReport::STATUS_DRAFT)->firstOrFail();
    $item = ExpenseItem::query()->where('code', 'DRINKING-WATER')->firstOrFail();
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)->post(route('daily-site-reports.expenses.store', $report), [
        'expense_item_id' => $item->id,
        'payee_type' => 'other',
        'payee_name' => 'Unauthorised provider',
        'quantity' => '2',
        'unit_amount' => '75000',
    ])->assertForbidden();

    $this->actingAs($director)->post(route('daily-site-reports.expenses.store', $report), [
        'expense_item_id' => $item->id,
        'payee_type' => 'other',
        'payee_name' => 'Missing quantity provider',
        'unit_amount' => '75000',
    ])->assertSessionHasErrors('quantity');

    $this->actingAs($director)->post(route('daily-site-reports.expenses.store', $report), [
        'expense_item_id' => $item->id,
        'payee_type' => 'other',
        'payee_name' => 'Field accommodation provider',
        'quantity' => '2',
        'unit_amount' => '75000',
        'description' => 'Overnight field allowance',
    ])->assertRedirect(route('daily-site-reports.show', $report));

    $expense = Expense::query()->where('payee_name_snapshot', 'Field accommodation provider')->firstOrFail();
    expect($expense->status)->toBe(ExpenseStatus::Draft)
        ->and($expense->daily_site_report_id)->toBe($report->id)
        ->and($expense->lines()->firstOrFail()->amount)->toBe('150000.0000');

    $this->actingAs($director)->put(route('expenses.update', $expense), [])->assertForbidden();
    $this->actingAs($director)->post(route('expenses.cancel', $expense), [
        'reason' => 'The cost was entered against the wrong report.',
    ])->assertRedirect();
    expect($expense->refresh()->status)->toBe(ExpenseStatus::Cancelled);
});
