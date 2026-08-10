<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\SubmitDailySiteReport;
use App\Models\DailySiteReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DailySiteReportSubmitController
{
    public function __invoke(DailySiteReport $dailySiteReport, SubmitDailySiteReport $action): RedirectResponse
    {
        Gate::authorize('submit', $dailySiteReport);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($dailySiteReport, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily site report submitted.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
