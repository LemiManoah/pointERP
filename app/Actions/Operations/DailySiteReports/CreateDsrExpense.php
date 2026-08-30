<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Actions\Operations\Expenses\SaveExpense;
use App\Http\Requests\Operations\DailySiteReports\StoreDsrExpenseRequest;
use App\Models\Branch;
use App\Models\DailySiteReport;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-import-type DsrExpensePayload from StoreDsrExpenseRequest */
final readonly class CreateDsrExpense
{
    public function __construct(
        private SaveExpense $saveExpense,
        private AuditLogger $auditLogger,
    ) {}

    /** @param DsrExpensePayload $data */
    public function handle(DailySiteReport $report, array $data, User $actor): Expense
    {
        return DB::transaction(function () use ($actor, $data, $report): Expense {
            $report = DailySiteReport::query()->lockForUpdate()->findOrFail($report->id);
            if (! $report->isEditable()) {
                throw ValidationException::withMessages(['expense' => 'Other costs can only be added while the DSR is editable.']);
            }

            $branch = Branch::query()->findOrFail($report->branch_id);
            $item = ExpenseItem::query()
                ->where('tenant_id', $report->tenant_id)
                ->where('is_active', true)
                ->findOrFail($data['expense_item_id']);
            if ($item->has_quantity && ! isset($data['quantity'])) {
                throw ValidationException::withMessages([
                    'quantity' => 'Enter a quantity for this expense item.',
                ]);
            }

            $expense = $this->saveExpense->handle([
                'branch_id' => $report->branch_id,
                'expense_date' => $report->report_date->toDateString(),
                'payee_type' => $data['payee_type'],
                'customer_id' => $data['customer_id'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'payee_name' => $data['payee_name'] ?? null,
                'currency_code' => $branch->default_currency_code,
                'description' => $data['description'] ?? 'Other cost recorded from '.$report->reference,
                'reference' => $report->reference,
                'lines' => [[
                    'expense_item_id' => $data['expense_item_id'],
                    'project_id' => $report->project_id,
                    'site_id' => $report->site_id,
                    'description' => $data['description'] ?? null,
                    'quantity' => $data['quantity'] ?? '1',
                    'unit_amount' => $data['unit_amount'],
                ]],
            ], $actor);
            $expense->forceFill(['daily_site_report_id' => $report->id])->save();
            $this->auditLogger->record('daily-site-reports.expense.created', $expense, $actor, [], ['daily_site_report_id' => $report->id], branch: $branch);

            return $expense;
        });
    }
}
