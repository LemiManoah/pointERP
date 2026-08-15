<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\ApproveDailySiteReportCorrection;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DailySiteReportCorrectionApprovalController
{
    public function __invoke(DailySiteReport $dailySiteReport, DailySiteReportCorrection $correction, ApproveDailySiteReportCorrection $action): RedirectResponse
    {
        abort_unless($correction->daily_site_report_id === $dailySiteReport->id, 404);
        Gate::authorize('approveCorrection', [$dailySiteReport, $correction]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($correction, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Correction approved and applied.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
