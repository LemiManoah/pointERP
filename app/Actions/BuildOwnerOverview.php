<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ExpensePaymentStatus;
use App\Enums\ExpenseStatus;
use App\Enums\PosPaymentStatus;
use App\Enums\PosSaleStatus;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\PosPayment;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\ProjectBudgetReport;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final readonly class BuildOwnerOverview
{
    public function __construct(
        private BuildDashboard $dashboard,
        private BranchContext $branches,
        private ProjectBudgetReport $projectBudgets,
    ) {}

    /**
     * @param  array{period?: string, from?: string|null, to?: string|null, currency?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        $dashboard = $this->dashboard->handle($user, $filters);
        $currency = $dashboard['filters']['currency'];
        $timezone = $user->tenant->timezone ?: config('app.timezone');
        $from = CarbonImmutable::parse($dashboard['filters']['from'], $timezone)->startOfDay();
        $to = CarbonImmutable::parse($dashboard['filters']['to'], $timezone)->addDay()->startOfDay();
        $start = $from->setTimezone(config('app.timezone'));
        $end = $to->setTimezone(config('app.timezone'));
        $budget = $this->projectBudgets->for($user, $currency);
        $activeProjects = collect($budget['rows'])->where('status', 'active');
        $activeBudgets = $activeProjects->whereNotNull('budget');
        $comparable = $activeBudgets->every(fn (array $project): bool => $project['spent'] !== null);
        $budgetTotal = $this->sum($activeBudgets->pluck('budget')->all());
        $incurredTotal = $comparable ? $this->sum($activeBudgets->pluck('spent')->all()) : null;

        $expenses = $this->scope(Expense::query(), $user)
            ->where('status', ExpenseStatus::Approved)
            ->where('currency_code', $currency)
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<', $to->toDateString())
            ->orderByDesc('expense_date')->get()
            ->filter(fn (Expense $expense): bool => Gate::forUser($user)->allows('view', $expense));

        $receipts = $this->scope(PosPayment::query(), $user)
            ->where('currency_code', $currency)
            ->where('status', PosPaymentStatus::Recorded)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end)
            ->whereHas('sale', fn (Builder $query): Builder => $query->whereIn('status', [PosSaleStatus::Completed, PosSaleStatus::PartiallyReturned, PosSaleStatus::Returned]))
            ->with('sale')->orderByDesc('recorded_at')->get();

        $payments = $this->scope(ExpensePayment::query(), $user)
            ->where('currency_code', $currency)
            ->where('status', ExpensePaymentStatus::Recorded)
            ->where('paid_at', '>=', $start)
            ->where('paid_at', '<', $end)
            ->with('expense')->orderByDesc('paid_at')->get()
            ->filter(fn (ExpensePayment $payment): bool => $payment->expense instanceof Expense && Gate::forUser($user)->allows('view', $payment->expense));

        $approvedExpenses = '0.0000';
        foreach ($dashboard['cards'] as $card) {
            if (is_array($card) && ($card['id'] ?? null) === 'expenses') {
                $approvedExpenses = $card['amount'];
                break;
            }
        }
        $cashFlow = $dashboard['cashFlow'];
        $exceptions = [];
        foreach ([$dashboard['operationalCards'], $dashboard['workQueues']] as $cards) {
            foreach ($cards as $card) {
                if (in_array($card['id'], ['low-stock', 'dsr-approval', 'dsr-review', 'orders-open', 'maintenance-open', 'equipment-unavailable'], true) && ($card['count'] ?? 0) > 0) {
                    $exceptions[] = $card;
                }
            }
        }

        return [
            'filters' => $dashboard['filters'],
            'currencies' => $dashboard['currencies'],
            'branch' => $this->branches->current($user)?->name ?: 'Accessible branches',
            'generatedAt' => now()->toIso8601String(),
            'summary' => [
                'approvedExpenses' => $approvedExpenses,
                'moneyIn' => $cashFlow['received']['amount'],
                'moneyOut' => $cashFlow['paid']['amount'],
                'activeProjectBudgets' => $budgetTotal,
                'incurredProjectCost' => $incurredTotal,
                'activeProjectCount' => $activeProjects->count(),
                'uncomparableProjectCount' => $activeBudgets->count() - $activeBudgets->filter(fn (array $project): bool => $project['spent'] !== null)->count(),
            ],
            'exceptions' => $exceptions,
            'sources' => [
                'expenses' => [
                    'count' => $expenses->count(),
                    'rows' => $expenses->take(25)->map(fn (Expense $expense): array => [
                        'id' => $expense->id, 'date' => $expense->expense_date->toDateString(), 'reference' => $expense->expense_number,
                        'description' => $expense->payee_name_snapshot, 'amount' => $expense->total_amount,
                        'href' => route('expenses.show', $expense),
                    ])->values()->all(),
                ],
                'receipts' => [
                    'count' => $receipts->count(),
                    'rows' => $receipts->take(25)->map(fn (PosPayment $payment): array => [
                        'id' => $payment->id, 'date' => $payment->recorded_at->setTimezone($timezone)->toDateString(), 'reference' => $payment->payment_number,
                        'description' => $payment->sale->sale_number, 'amount' => $payment->amount,
                        'href' => route('pos.show', $payment->pos_sale_id),
                    ])->values()->all(),
                ],
                'payments' => [
                    'count' => $payments->count(),
                    'rows' => $payments->take(25)->map(fn (ExpensePayment $payment): array => [
                        'id' => $payment->id, 'date' => $payment->paid_at->setTimezone($timezone)->toDateString(), 'reference' => $payment->payment_number,
                        'description' => $payment->expense->expense_number, 'amount' => $payment->amount,
                        'href' => route('expenses.show', $payment->expense_id),
                    ])->values()->all(),
                ],
            ],
        ];
    }

    /**
     * @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function scope(Builder $query, User $user): Builder
    {
        $branch = $this->branches->current($user);

        return $query->where('tenant_id', $user->tenant_id)
            ->whereIn('branch_id', $branch instanceof Branch ? [$branch->id] : $this->branches->accessibleBranchIds($user));
    }

    /** @param list<string|null> $values */
    private function sum(array $values): string
    {
        $total = BigDecimal::zero();
        foreach ($values as $value) {
            $total = $total->plus($value ?? '0');
        }

        return (string) $total->toScale(4);
    }
}
