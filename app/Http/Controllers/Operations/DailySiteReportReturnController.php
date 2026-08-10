<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\ReturnDailySiteReport;
use App\Http\Requests\Operations\DailySiteReports\ReturnDailySiteReportRequest;
use App\Models\DailySiteReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DailySiteReportReturnController
{
    public function __invoke(ReturnDailySiteReportRequest $request, DailySiteReport $dailySiteReport, ReturnDailySiteReport $action): RedirectResponse
    {
        Gate::authorize('return', $dailySiteReport);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($dailySiteReport, $actor, (string) $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily site report returned.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
