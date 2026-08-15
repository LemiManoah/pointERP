<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\RejectDailySiteReportCorrection;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DailySiteReportCorrectionRejectionController
{
    public function __invoke(Request $request, DailySiteReport $dailySiteReport, DailySiteReportCorrection $correction, RejectDailySiteReportCorrection $action): RedirectResponse
    {
        abort_unless($correction->daily_site_report_id === $dailySiteReport->id, 404);
        Gate::authorize('rejectCorrection', [$dailySiteReport, $correction]);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action->handle($correction, $actor, (string) $validated['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Correction rejected.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
