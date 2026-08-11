<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\SubmitDailySiteReport;
use App\Http\Requests\Operations\DailySiteReports\SubmitDailySiteReportRequest;
use App\Models\DailySiteReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DailySiteReportSubmitController
{
    public function __invoke(SubmitDailySiteReportRequest $request, DailySiteReport $dailySiteReport, SubmitDailySiteReport $action): RedirectResponse
    {
        Gate::authorize('submit', $dailySiteReport);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($dailySiteReport, $actor, $request->validated('evidence_override_reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily site report submitted.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
