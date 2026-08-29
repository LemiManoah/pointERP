<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Expenses\SaveExpense;
use App\Http\Requests\Operations\Expenses\StoreExpenseRequest;
use App\Models\DailySiteReportCostLine;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\ExpenseLine;
use App\Models\ExpensePayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\ExpenseFormOptions;
use App\Support\Operations\PresentsLinkedDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** @phpstan-import-type ExpensePayload from StoreExpenseRequest */
final class ExpenseController
{
    use PresentsLinkedDocuments;

    public function index(Request $request, ExpenseFormOptions $options): Response
    {
        Gate::authorize('viewAny', Expense::class);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user);
        $canViewCosts = $user->can('expenses.view-costs');

        $expenses = Expense::query()
            ->with(['branch', 'lines.project', 'payments'])
            ->whereIn('branch_id', $branchIds)
            ->latest('expense_date')
            ->get()
            ->filter(fn (Expense $expense): bool => Gate::forUser($user)->allows('view', $expense))
            ->values();
        $payments = $user->can('expense-payments.view')
            ? ExpensePayment::query()->with(['expense', 'recorder'])->whereIn('branch_id', $branchIds)->latest('paid_at')->get()->filter(fn (ExpensePayment $payment): bool => Gate::forUser($user)->allows('view', $payment))->map(fn (ExpensePayment $payment): array => $this->paymentRow($payment, $canViewCosts))->values()
            : collect();

        return Inertia::render('operations/expenses/index', [
            'expenses' => $expenses->map(fn (Expense $expense): array => $this->expenseRow($expense, $canViewCosts)),
            'payments' => $payments,
            ...$options->for($user),
            'can' => [
                'create' => Gate::forUser($user)->allows('create', Expense::class),
                'manageCategories' => Gate::forUser($user)->allows('create', ExpenseCategory::class),
                'manageItems' => Gate::forUser($user)->allows('create', ExpenseItem::class),
                'viewCosts' => $canViewCosts,
                'viewPayments' => $user->can('expense-payments.view'),
                'export' => $user->can('expenses.export') && $canViewCosts,
            ],
        ]);
    }

    public function create(Request $request, ExpenseFormOptions $options): Response
    {
        Gate::authorize('create', Expense::class);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('operations/expenses/form', [
            'expense' => null,
            ...$options->for($user),
            'canRecordPayment' => $user->can('expense-payments.record'),
        ]);
    }

    public function store(StoreExpenseRequest $request, SaveExpense $action): RedirectResponse
    {
        Gate::authorize('create', Expense::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        /** @var ExpensePayload $data */
        $data = $request->validated();
        $expense = $action->handle($data, $actor, canRecordPayment: $actor->can('expense-payments.record'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense draft saved.']);

        return to_route('expenses.show', $expense);
    }

    public function show(Request $request, Expense $expense, ExpenseFormOptions $options): Response
    {
        Gate::authorize('view', $expense);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $canViewCosts = Gate::forUser($user)->allows('viewCosts', $expense);
        $expense->load(['branch', 'customer', 'staff', 'lines.item.category', 'lines.project', 'lines.site', 'lines.activity', 'lines.dsrReconciliation.dsrCostLine.report', 'payments.recorder', 'payments.reverser']);
        $projectIds = $expense->lines->pluck('project_id')->filter();

        return Inertia::render('operations/expenses/show', [
            'expense' => $this->expenseDetail($expense, $canViewCosts),
            'documents' => $this->linkedDocumentsFor($expense, $user),
            'dsrCostLines' => DailySiteReportCostLine::query()->with('report')->whereHas('report', fn (Builder $query): Builder => $query->whereIn('project_id', $projectIds))->whereDoesntHave('expenseReconciliation')->latest()->get()->map(fn (DailySiteReportCostLine $line): array => ['value' => $line->id, 'label' => $line->report->reference.' - '.$line->description, 'amount' => $canViewCosts ? $line->amount : null]),
            ...$options->for($user),
            ...$this->documentFormOptions($user),
            'can' => [
                'update' => Gate::forUser($user)->allows('update', $expense),
                'submit' => Gate::forUser($user)->allows('submit', $expense),
                'approve' => Gate::forUser($user)->allows('approve', $expense),
                'reject' => Gate::forUser($user)->allows('reject', $expense),
                'cancel' => Gate::forUser($user)->allows('cancel', $expense),
                'delete' => Gate::forUser($user)->allows('delete', $expense),
                'recordPayment' => Gate::forUser($user)->allows('recordPayment', $expense),
                'reversePayments' => $user->can('expense-payments.reverse'),
                'reconcileDsr' => Gate::forUser($user)->allows('reconcileDsr', $expense),
                'viewCosts' => $canViewCosts,
                'uploadDocuments' => Gate::forUser($user)->allows('create', Document::class),
            ],
        ]);
    }

    public function edit(Request $request, Expense $expense, ExpenseFormOptions $options): Response
    {
        Gate::authorize('update', $expense);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $expense->load('lines');

        return Inertia::render('operations/expenses/form', ['expense' => $this->expenseForm($expense), ...$options->for($user), 'canRecordPayment' => false]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense, SaveExpense $action): RedirectResponse
    {
        Gate::authorize('update', $expense);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        /** @var ExpensePayload $data */
        $data = $request->validated();
        $action->handle($data, $actor, $expense);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense draft updated.']);

        return to_route('expenses.show', $expense);
    }

    public function destroy(Expense $expense, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $expense);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $auditLogger->record('expenses.draft.deleted', $expense, $actor, $expense->load('lines')->toArray(), [], branch: $expense->branch);
        $expense->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense draft deleted.']);

        return to_route('expenses.index');
    }

    /** @return array<string, mixed> */
    private function expenseRow(Expense $expense, bool $canViewCosts): array
    {
        $projects = $expense->lines
            ->map(fn (ExpenseLine $line): ?string => $line->project?->name)
            ->filter()
            ->unique()
            ->values()
            ->join(', ');

        return ['id' => $expense->id, 'expense_number' => $expense->expense_number, 'expense_date' => $expense->expense_date->toDateString(), 'payee' => $expense->payee_name_snapshot, 'branch' => $expense->branch->name, 'reference' => $expense->reference, 'status' => $expense->status->value, 'status_label' => $expense->status->label(), 'currency_code' => $expense->currency_code, 'total_amount' => $canViewCosts ? $expense->total_amount : null, 'paid_amount' => $canViewCosts ? number_format($expense->paidAmount(), 4, '.', '') : null, 'balance' => $canViewCosts ? number_format($expense->balance(), 4, '.', '') : null, 'payment_status' => $expense->paymentStatus(), 'projects' => $projects];
    }

    /** @return array<string, mixed> */
    private function expenseDetail(Expense $expense, bool $canViewCosts): array
    {
        return [...$this->expenseRow($expense, $canViewCosts), 'branch_id' => $expense->branch_id, 'payee_type' => $expense->payee_type->value, 'customer_id' => $expense->customer_id, 'staff_id' => $expense->staff_id, 'description' => $expense->description, 'decision_reason' => $expense->decision_reason, 'base_currency_code' => $expense->base_currency_code, 'exchange_rate' => $canViewCosts ? $expense->exchange_rate : null, 'lines' => $expense->lines->map(fn (ExpenseLine $line): array => ['id' => $line->id, 'expense_item_id' => $line->expense_item_id, 'category' => $line->expense_category_name_snapshot, 'item' => $line->expense_item_name_snapshot, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_amount' => $canViewCosts ? $line->unit_amount : null, 'amount' => $canViewCosts ? $line->amount : null, 'project' => $line->project?->name, 'site' => $line->site?->name, 'work_item' => $line->activity?->name, 'reconciliation' => $line->dsrReconciliation ? ['id' => $line->dsrReconciliation->id, 'dsr_reference' => $line->dsrReconciliation->dsrCostLine?->report->reference ?? 'DSR cost'] : null]), 'payments' => $canViewCosts ? $expense->payments->map(fn (ExpensePayment $payment): array => $this->paymentRow($payment, true)) : []];
    }

    /** @return array<string, mixed> */
    private function expenseForm(Expense $expense): array
    {
        return ['id' => $expense->id, 'branch_id' => $expense->branch_id, 'expense_date' => $expense->expense_date->toDateString(), 'payee_type' => $expense->payee_type->value, 'customer_id' => $expense->customer_id, 'staff_id' => $expense->staff_id, 'payee_name' => $expense->payee_type->value === 'other' ? $expense->payee_name_snapshot : null, 'currency_code' => $expense->currency_code, 'description' => $expense->description, 'reference' => $expense->reference, 'lines' => $expense->lines->map(fn (ExpenseLine $line): array => ['expense_item_id' => $line->expense_item_id, 'project_id' => $line->project_id, 'site_id' => $line->site_id, 'project_activity_id' => $line->project_activity_id, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_amount' => $line->unit_amount])];
    }

    /** @return array<string, mixed> */
    private function paymentRow(ExpensePayment $payment, bool $canViewCosts): array
    {
        $expense = $payment->expense;
        abort_unless($expense instanceof Expense, 500);

        return ['id' => $payment->id, 'expense_id' => $payment->expense_id, 'expense_number' => $expense->expense_number, 'payment_number' => $payment->payment_number, 'paid_at' => $payment->paid_at->toDateTimeString(), 'amount' => $canViewCosts ? $payment->amount : null, 'currency_code' => $payment->currency_code, 'payment_method' => $payment->payment_method->label(), 'reference' => $payment->reference, 'status' => $payment->status->value, 'recorded_by' => $payment->recorder?->name, 'reversal_reason' => $payment->reversal_reason];
    }
}
