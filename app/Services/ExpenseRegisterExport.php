<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final readonly class ExpenseRegisterExport
{
    public function __construct(private BranchContext $branchContext) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function for(User $user, array $filters = []): array
    {
        $expenses = Expense::query()
            ->with(['branch', 'lines.project', 'payments'])
            ->whereIn('branch_id', $this->branchContext->accessibleBranchIds($user))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('expense_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('expense_date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['branch'] ?? null, fn ($query, $branch) => $query->whereHas('branch', fn ($branchQuery) => $branchQuery->where('name', $branch)))
            ->when($filters['project'] ?? null, fn ($query, $project) => $query->whereHas('lines.project', fn ($projectQuery) => $projectQuery->where('name', $project)))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas('lines', fn ($lineQuery) => $lineQuery->where('expense_category_name_snapshot', $category)))
            ->latest('expense_date')
            ->get()
            ->filter(fn (Expense $expense): bool => Gate::forUser($user)->allows('view', $expense))
            ->values();

        return [
            'headers' => ['Expense', 'Date', 'Branch', 'Payee', 'Projects', 'Reference', 'Currency', 'Total', 'Paid', 'Balance', 'Approval status', 'Payment status'],
            'rows' => $expenses->map(function (Expense $expense): array {
                $projects = $expense->lines
                    ->map(fn (ExpenseLine $line): ?string => $line->project?->name)
                    ->filter()
                    ->unique()
                    ->join(', ');

                return [
                    $expense->expense_number,
                    $expense->expense_date->toDateString(),
                    $expense->branch->name,
                    $expense->payee_name_snapshot,
                    $projects ?: 'Branch overhead',
                    $expense->reference ?? '',
                    $expense->currency_code,
                    $expense->total_amount,
                    number_format($expense->paidAmount(), 4, '.', ''),
                    number_format($expense->balance(), 4, '.', ''),
                    $expense->status->label(),
                    str($expense->paymentStatus())->replace('_', ' ')->title()->toString(),
                ];
            })->values()->all(),
        ];
    }
}
