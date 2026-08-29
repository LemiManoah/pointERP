<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Expenses\ReconcileDsrExpense;
use App\Http\Requests\Operations\Expenses\ReconcileDsrExpenseRequest;
use App\Models\DailySiteReportCostLine;
use App\Models\ExpenseLine;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DsrExpenseReconciliationController
{
    public function __invoke(ReconcileDsrExpenseRequest $request, ExpenseLine $expenseLine, ReconcileDsrExpense $action): RedirectResponse
    {
        $expenseLine->loadMissing('expense');
        Gate::authorize('reconcileDsr', $expenseLine->expense);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $dsrLine = DailySiteReportCostLine::query()->findOrFail((string) $request->validated('daily_site_report_cost_line_id'));
        $action->handle($expenseLine, $dsrLine, $actor, (string) $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'DSR cost reconciled to this expense.']);

        return to_route('expenses.show', $expenseLine->expense_id);
    }
}
