<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\ApproveDailySiteReport;
use App\Models\DailySiteReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DailySiteReportApprovalController
{
    public function __invoke(DailySiteReport $dailySiteReport, ApproveDailySiteReport $action): RedirectResponse
    {
        Gate::authorize('approve', $dailySiteReport);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($dailySiteReport, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily site report approved.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
