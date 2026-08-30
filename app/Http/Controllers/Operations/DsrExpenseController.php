<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\CreateDsrExpense;
use App\Http\Requests\Operations\DailySiteReports\StoreDsrExpenseRequest;
use App\Models\DailySiteReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** @phpstan-import-type DsrExpensePayload from StoreDsrExpenseRequest */
final class DsrExpenseController
{
    public function __invoke(StoreDsrExpenseRequest $request, DailySiteReport $dailySiteReport, CreateDsrExpense $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        /** @var DsrExpensePayload $data */
        $data = $request->validated();
        $action->handle($dailySiteReport, $data, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense draft created from the DSR.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
