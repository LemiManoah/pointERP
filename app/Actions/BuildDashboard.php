<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpensePaymentStatus;
use App\Enums\ExpenseStatus;
use App\Enums\PosPaymentStatus;
use App\Enums\PosSaleStatus;
use App\Models\Branch;
use App\Models\DailySiteReport;
use App\Models\Equipment;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\MaterialRequisition;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Site;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryOperationsReport;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class BuildDashboard
{
    public function __construct(private BranchContext $branches, private InventoryOperationsReport $inventory) {}

    /**
     * @param  array{period?: string, from?: string|null, to?: string|null, currency?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters = []): array
    {
        $timezone = $user->tenant->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $period = $filters['period'] ?? 'month';
        $from = match ($period) {
            'today' => $today,
            'last7' => $today->subDays(6),
            'quarter' => $today->startOfQuarter(),
            'range' => CarbonImmutable::parse($filters['from'], $timezone)->startOfDay(),
            default => $today->startOfMonth(),
        };
        $to = $period === 'range' ? CarbonImmutable::parse($filters['to'], $timezone)->startOfDay() : $today;
        $start = $from->setTimezone(config('app.timezone'));
        $end = $to->addDay()->setTimezone(config('app.timezone'));
        $canSales = $user->can('pos.view');
        $canExpenses = $user->can('expenses.view') && $user->can('expenses.view-costs');
        $canReceived = $canSales && $user->can('pos.view-payments');
        $canPaid = $canExpenses && $user->can('expense-payments.view');

        $sales = $canSales ? $this->scope(PosSale::query(), $user)
            ->whereIn('status', [PosSaleStatus::Completed, PosSaleStatus::PartiallyReturned, PosSaleStatus::Returned])
            ->unless($user->can('pos.view-all-sales'), fn (Builder $query): Builder => $query->where('sold_by', $user->id))
            ->get(['id', 'currency_code', 'total_amount', 'balance_due', 'completed_at']) : collect();
        $expenses = $canExpenses ? $this->scope(Expense::query(), $user)->get()
            ->filter(fn (Expense $expense): bool => Gate::forUser($user)->allows('view', $expense)) : collect();
        $receipts = $canReceived ? $this->scope(PosPayment::query(), $user)
            ->whereIn('pos_sale_id', $sales->modelKeys())->where('status', PosPaymentStatus::Recorded)
            ->where('recorded_at', '>=', $start)->where('recorded_at', '<', $end)->get() : collect();
        $payments = $canPaid ? $this->scope(ExpensePayment::query(), $user)
            ->whereIn('expense_id', $expenses->modelKeys())->where('status', ExpensePaymentStatus::Recorded)->get() : collect();
        $periodPayments = $payments->filter(fn (ExpensePayment $payment): bool => $payment->paid_at->gte($start) && $payment->paid_at->lt($end));

        $currencies = $sales->pluck('currency_code')->merge($expenses->pluck('currency_code'))
            ->merge($receipts->pluck('currency_code'))->merge($periodPayments->pluck('currency_code'))->unique()->sort()->values();
        $defaultCurrency = $this->branches->current($user)?->default_currency_code ?? $user->tenant->default_currency_code;
        if ($currencies->isEmpty()) {
            $currencies = collect([$defaultCurrency]);
        }

        $currency = $filters['currency'] ?? ($currencies->contains($defaultCurrency) ? $defaultCurrency : $currencies->first());
        if ($currencies->doesntContain($currency)) {
            throw ValidationException::withMessages(['currency' => 'Select an available currency.']);
        }

        $sales = $sales->where('currency_code', $currency);
        $approved = $expenses->where('currency_code', $currency)->filter(fn (Expense $expense): bool => $expense->status === ExpenseStatus::Approved);
        $periodSales = $sales->filter(fn (PosSale $sale): bool => $sale->completed_at !== null && $sale->completed_at->gte($start) && $sale->completed_at->lt($end));
        $periodExpenses = $approved->filter(fn (Expense $expense): bool => $expense->expense_date->toDateString() >= $from->toDateString() && $expense->expense_date->toDateString() <= $to->toDateString());
        $receipts = $receipts->where('currency_code', $currency);
        $periodPayments = $periodPayments->where('currency_code', $currency);
        $cards = [];
        if ($canSales) {
            $cards[] = $this->card('sales', 'Sales', $this->sum($periodSales, 'total_amount'), $periodSales->count(), 'Value of completed POS sales in this period, before returns', route('pos.index'));
        }

        if ($canExpenses) {
            $subtitle = null;
            if ($canPaid) {
                $paidByExpense = $payments->groupBy('expense_id');
                $paidCount = $periodExpenses->filter(fn (Expense $expense): bool => BigDecimal::of($this->sum($paidByExpense->get($expense->id, collect()), 'amount'))->isGreaterThanOrEqualTo($expense->total_amount))->count();
                $subtitle = $paidCount.' paid · '.($periodExpenses->count() - $paidCount).' unpaid';
            }
            $cards[] = [...$this->card('expenses', 'Expenses', $this->sum($periodExpenses, 'total_amount'), $periodExpenses->count(), 'Approved costs in the selected period. Paid means fully settled by recorded payments; unpaid includes partly paid expenses. Payment status is current.', route('expenses.index')), 'subtitle' => $subtitle];
        }

        if ($canSales) {
            $outstanding = $sales->filter(fn (PosSale $sale): bool => BigDecimal::of($sale->balance_due)->isGreaterThan(0));
            $cards[] = $this->card('receivables', 'Customers owe', $this->sum($outstanding, 'balance_due'), $outstanding->count(), 'Money customers still owe on completed POS sales, across all dates', route('pos.index'), true);
        }

        if ($canPaid) {
            $paidByExpense = $payments->groupBy('expense_id');
            $balances = $approved->map(function (Expense $expense) use ($paidByExpense): string {
                $paid = $this->sum($paidByExpense->get($expense->id, collect()), 'amount');
                $balance = BigDecimal::of($expense->total_amount)->minus($paid);

                return (string) ($balance->isNegative() ? BigDecimal::zero() : $balance)->toScale(4);
            })->filter(fn (string $amount): bool => BigDecimal::of($amount)->isGreaterThan(0));
            $cards[] = $this->card('payables', 'Unpaid expenses', $this->sumValues($balances), $balances->count(), 'Approved expenses still to pay, across all dates', route('expenses.index'), true);
        }

        $result = [
            'filters' => ['period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'currency' => $currency],
            'currencies' => $currencies->all(),
            'cards' => $cards,
            ...$this->operations($user),
        ];
        if ($canReceived || $canPaid) {
            $result['cashFlow'] = $this->cashFlow($receipts, $periodPayments, $from, $to, $timezone, $canReceived, $canPaid);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function operations(User $user): array
    {
        $cards = [];
        $gate = Gate::forUser($user);
        if ($user->can('dashboards.projects.view') && $gate->allows('viewAny', Project::class)) {
            $projects = $this->scope(Project::query(), $user)->where('status', 'active')->get()
                ->filter(fn (Project $project): bool => $gate->allows('view', $project));
            $count = $projects->count();
            $sites = $gate->allows('viewAny', Site::class)
                ? $this->scope(Site::query(), $user)->whereIn('project_id', $projects->modelKeys())->with('project')->get()->filter(fn (Site $site): bool => $gate->allows('view', $site))->count()
                : null;
            $cards[] = ['id' => 'projects', 'title' => 'Active projects', 'count' => $count, 'subtitle' => $sites === null ? null : $sites.' '.($sites === 1 ? 'site' : 'sites'), 'description' => 'Currently active projects you can access, with the number of visible sites belonging to those projects.', 'href' => route('projects.index')];
        }

        if ($user->can('daily-site-reports.view-dashboard') && $gate->allows('viewAny', DailySiteReport::class)) {
            $pending = $this->scope(DailySiteReport::query(), $user)
                ->whereIn('status', [DailySiteReport::STATUS_SUBMITTED, DailySiteReport::STATUS_REVIEWED])
                ->with(['site.project', 'project'])->get();
            if ($user->can('daily-site-reports.approve')) {
                $visibleReports = $pending->filter(fn (DailySiteReport $report): bool => $gate->allows('approve', $report));
                $count = $visibleReports->count();
                $cards[] = ['id' => 'dsr-approval', 'title' => 'DSRs awaiting approval', 'count' => $count, 'subtitle' => $visibleReports->pluck('site_id')->unique()->count().' sites', 'description' => 'Submitted or reviewed site reports you can approve', 'href' => route('daily-site-reports.index')];
            } elseif ($user->can('daily-site-reports.review')) {
                $visibleReports = $pending->filter(fn (DailySiteReport $report): bool => $gate->allows('review', $report));
                $count = $visibleReports->count();
                $cards[] = ['id' => 'dsr-review', 'title' => 'DSRs awaiting review', 'count' => $count, 'subtitle' => $visibleReports->pluck('site_id')->unique()->count().' sites', 'description' => 'Submitted site reports that need your review', 'href' => route('daily-site-reports.index')];
            } else {
                $visibleReports = $pending->filter(fn (DailySiteReport $report): bool => $gate->allows('view', $report));
                $count = $visibleReports->count();
                $cards[] = ['id' => 'dsr-pending', 'title' => 'DSRs pending approval', 'count' => $count, 'subtitle' => $visibleReports->pluck('site_id')->unique()->count().' sites', 'description' => 'Visible submitted or reviewed reports awaiting sign-off', 'href' => route('daily-site-reports.index')];
            }
        }

        if ($user->can('equipment.dashboard.view') && $gate->allows('viewAny', Equipment::class)) {
            $statuses = $this->scope(Equipment::query(), $user)->where('is_active', true)->get(['current_status'])->countBy('current_status');
            $labels = ['out_of_service' => 'out of service', 'under_maintenance' => 'maintenance', 'assigned' => 'assigned', 'available' => 'available', 'idle' => 'idle', 'transferred' => 'transferred', 'retired' => 'retired'];
            $summary = collect($labels)->filter(fn (string $label, string $status): bool => $statuses->get($status, 0) > 0)
                ->take(3)->map(fn (string $label, string $status): string => $statuses->get($status).' '.$label)->implode(' · ');
            $cards[] = ['id' => 'equipment', 'title' => 'Active equipment', 'count' => $statuses->sum(), 'subtitle' => $summary ?: 'No active equipment', 'description' => 'Active equipment in your accessible branches. Up to three non-zero statuses are shown, prioritising out of service, maintenance, and assigned equipment.', 'href' => route('equipment.index')];
        }

        $result = [];
        if ($user->can('inventory.stock.view')) {
            $stock = $this->inventory->lowStockSummary($user, $this->branches->current($user)?->id);
            $cards[] = ['id' => 'low-stock', 'title' => 'Low stock', 'count' => $stock['count'], 'nearExpiry' => $stock['nearExpiry'], 'expired' => $stock['expired'], 'description' => 'Current item/store counts: low stock is available quantity at or below minimum. Near expiry is today through the next 30 days; expired is before today. Expiry counts include only batches with stock remaining. Categories can overlap.', 'href' => route('inventory.dashboard')];
        }

        return ['operationalCards' => $cards, 'workQueues' => $this->workQueues($user), ...$result];
    }

    /** @return list<array<string, mixed>> */
    private function workQueues(User $user): array
    {
        $queues = [];
        $gate = Gate::forUser($user);
        if ($user->can('daily-site-reports.create') && $gate->allows('viewAny', DailySiteReport::class)) {
            $reports = $this->scope(DailySiteReport::query(), $user)
                ->whereIn('status', [DailySiteReport::STATUS_DRAFT, DailySiteReport::STATUS_RETURNED])
                ->with(['site.project', 'project'])->get()
                ->filter(fn (DailySiteReport $report): bool => $gate->allows('view', $report));
            $queues[] = ['id' => 'dsr-drafts', 'title' => 'DSRs to finish', 'count' => $reports->where('status', DailySiteReport::STATUS_DRAFT)->count(), 'description' => 'Visible draft site reports not yet submitted', 'href' => route('daily-site-reports.index')];
            $queues[] = ['id' => 'dsr-returned', 'title' => 'DSRs returned', 'count' => $reports->where('status', DailySiteReport::STATUS_RETURNED)->count(), 'description' => 'Visible reports returned for corrections', 'href' => route('daily-site-reports.index')];
        }

        if ($gate->allows('viewAny', MaterialRequisition::class)) {
            $count = $this->scope(MaterialRequisition::query(), $user)->whereIn('status', ['approved', 'partially_issued'])->count();
            $queues[] = ['id' => 'requisitions-issue', 'title' => 'Material requests awaiting issue', 'count' => $count, 'description' => 'Approved or partly issued requests still awaiting fulfilment', 'href' => route('inventory.requisitions.index')];
        }

        if ($gate->allows('viewAny', PurchaseOrder::class)) {
            $orders = $this->scope(PurchaseOrder::query(), $user)->whereIn('status', ['approved', 'partially_received']);
            $queues[] = ['id' => 'orders-open', 'title' => 'Purchase orders awaiting receipt', 'count' => (clone $orders)->count(), 'description' => 'Approved or partly received orders still open', 'href' => route('inventory.purchase-orders.index')];
        }

        if ($user->can('equipment.dashboard.view') && $gate->allows('viewAny', Equipment::class)) {
            $count = $this->scope(Equipment::query(), $user)->where('is_active', true)->whereIn('current_status', ['under_maintenance', 'out_of_service'])->count();
            $queues[] = ['id' => 'equipment-unavailable', 'title' => 'Equipment unavailable', 'count' => $count, 'description' => 'Active assets under maintenance or out of service', 'href' => route('equipment.index')];
            if ($gate->allows('viewAny', EquipmentMaintenanceWorkOrder::class)) {
                $count = $this->scope(EquipmentMaintenanceWorkOrder::query(), $user)->whereIn('status', ['planned', 'approved', 'in_progress'])->count();
                $queues[] = ['id' => 'maintenance-open', 'title' => 'Open maintenance jobs', 'count' => $count, 'description' => 'Planned, approved, or ongoing equipment maintenance', 'href' => route('equipment.index')];
            }
        }

        return $queues;
    }

    /**
     * @param  Collection<int, PosPayment>  $receipts
     * @param  Collection<int, ExpensePayment>  $payments
     * @return array<string, mixed>
     */
    private function cashFlow(Collection $receipts, Collection $payments, CarbonImmutable $from, CarbonImmutable $to, string $timezone, bool $canReceived, bool $canPaid): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $interval = $days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month');
        $keyFor = fn (CarbonImmutable $date): string => match ($interval) {
            'month' => $date->format('Y-m'),
            'week' => (string) intdiv((int) $from->diffInDays($date->startOfDay()), 7),
            default => $date->toDateString(),
        };
        $series = [];
        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $key = $keyFor($date);
            if (! isset($series[$key])) {
                $series[$key] = ['label' => $date->format($interval === 'month' ? 'M Y' : 'd M')];
                if ($canReceived) {
                    $series[$key]['received'] = '0.0000';
                }

                if ($canPaid) {
                    $series[$key]['paid'] = '0.0000';
                }
            }
        }

        $methods = [];
        foreach (['received' => $receipts, 'paid' => $payments] as $direction => $records) {
            foreach ($records as $record) {
                $date = CarbonImmutable::instance($direction === 'received' ? $record->recorded_at : $record->paid_at)->setTimezone($timezone);
                $key = $keyFor($date);
                $series[$key][$direction] = (string) BigDecimal::of($series[$key][$direction])->plus($record->amount)->toScale(4);
                $method = $direction === 'received' ? $record->method->value : $record->payment_method->value;
                if (! isset($methods[$method])) {
                    $methods[$method] = ['method' => $method, 'label' => ExpensePaymentMethod::from($method)->label()];
                    if ($canReceived) {
                        $methods[$method] += ['received' => '0.0000', 'receivedCount' => 0];
                    }

                    if ($canPaid) {
                        $methods[$method] += ['paid' => '0.0000', 'paidCount' => 0];
                    }
                }

                $methods[$method][$direction] = (string) BigDecimal::of($methods[$method][$direction])->plus($record->amount)->toScale(4);
                $methods[$method][$direction.'Count']++;
            }
        }

        $result = ['series' => array_values($series), 'methods' => array_values($methods), 'interval' => $interval];
        if ($canReceived) {
            $result['received'] = ['amount' => $this->sum($receipts, 'amount'), 'count' => $receipts->count()];
        }

        if ($canPaid) {
            $result['paid'] = ['amount' => $this->sum($payments, 'amount'), 'count' => $payments->count()];
        }

        if ($canReceived && $canPaid) {
            $result['net'] = (string) BigDecimal::of($result['received']['amount'])->minus($result['paid']['amount'])->toScale(4);
        }

        return $result;
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function scope(Builder $query, User $user): Builder
    {
        $current = $this->branches->current($user);

        return $query->where('tenant_id', $user->tenant_id)
            ->whereIn('branch_id', $current instanceof Branch ? [$current->id] : $this->branches->accessibleBranchIds($user));
    }

    /** @param Collection<array-key, mixed> $records */
    private function sum(Collection $records, string $field): string
    {
        return $this->sumValues($records->pluck($field));
    }

    /** @param Collection<array-key, mixed> $values */
    private function sumValues(Collection $values): string
    {
        $total = BigDecimal::zero();
        foreach ($values as $value) {
            $total = $total->plus($value ?? '0');
        }

        return (string) $total->toScale(4);
    }

    /** @return array<string, mixed> */
    private function card(string $id, string $title, string $amount, int $count, string $description, string $href, bool $snapshot = false): array
    {
        return ['id' => $id, 'title' => $title, 'amount' => $amount, 'count' => $count, 'description' => $description, 'href' => $href, 'snapshot' => $snapshot];
    }
}
