<?php

declare(strict_types=1);

namespace App\Actions\Operations\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\DailySiteReportCostLine;
use App\Models\DsrExpenseReconciliation;
use App\Models\ExpenseLine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

final readonly class ReconcileDsrExpense
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(ExpenseLine $line, DailySiteReportCostLine $dsrLine, User $actor, string $reason): DsrExpenseReconciliation
    {
        $line->loadMissing('expense');
        $dsrLine->loadMissing('report');
        if ($line->expense->status !== ExpenseStatus::Approved || $line->project_id === null || $line->project_id !== $dsrLine->report->project_id) {
            throw ValidationException::withMessages(['daily_site_report_cost_line_id' => 'Select an approved expense line and DSR cost from the same project.']);
        }

        if ($line->dsrReconciliation()->exists() || DsrExpenseReconciliation::query()->where('daily_site_report_cost_line_id', $dsrLine->id)->exists()) {
            throw ValidationException::withMessages(['daily_site_report_cost_line_id' => 'That expense line or DSR cost is already reconciled.']);
        }

        $reconciliation = DsrExpenseReconciliation::query()->create([
            'tenant_id' => $line->tenant_id,
            'daily_site_report_cost_line_id' => $dsrLine->id,
            'expense_line_id' => $line->id,
            'reconciled_by' => $actor->id,
            'reconciled_at' => now(),
            'reason' => $reason,
        ]);
        $this->auditLogger->record('expenses.dsr.reconciled', $reconciliation, $actor, [], $reconciliation->toArray(), $reason, $line->expense->branch);

        return $reconciliation;
    }
}
