<?php

declare(strict_types=1);

use App\Enums\ExpensePaymentStatus;
use App\Enums\ExpenseStatus;
use App\Models\DsrExpenseReconciliation;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\ExpensePayment;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectPerformanceSummary;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
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

it('matches an approved project expense to a DSR cost without double counting it', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $reconciliation = DsrExpenseReconciliation::query()->whereHas('expenseLine.expense', fn (Builder $query): Builder => $query->where('expense_number', 'EXP-DEMO-002'))->firstOrFail();
    $summary = resolve(ProjectPerformanceSummary::class)->forProject($project, true);
    assert(is_array($summary));

    expect($reconciliation->expenseLine->amount)->toBe('450000.0000')
        ->and($summary['totals']['operational_expenses'])->toBe('450000.0000');
});
