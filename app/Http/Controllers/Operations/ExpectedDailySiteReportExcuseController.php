<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Requests\Operations\ExpectedDailySiteReports\ExcuseExpectedDailySiteReportRequest;
use App\Models\ExpectedDailySiteReport;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpectedDailySiteReportExcuseController
{
    public function __invoke(
        ExcuseExpectedDailySiteReportRequest $request,
        ExpectedDailySiteReport $expectedDailySiteReport,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can('expected-daily-reports.excuse'), 403);
        abort_unless($expectedDailySiteReport->site instanceof Site, 404);
        Gate::forUser($actor)->authorize('view', $expectedDailySiteReport->site);
        abort_unless(in_array($expectedDailySiteReport->status, [
            ExpectedDailySiteReport::STATUS_EXPECTED,
            ExpectedDailySiteReport::STATUS_MISSING,
        ], true), 422, 'Only expected or missing obligations can be excused.');

        $oldValues = $expectedDailySiteReport->only(['status', 'excuse_reason', 'marked_by', 'marked_at']);
        $reason = $request->string('reason')->toString();
        $expectedDailySiteReport->forceFill([
            'status' => ExpectedDailySiteReport::STATUS_EXCUSED,
            'excuse_reason' => $reason,
            'marked_by' => $actor->id,
            'marked_at' => now(),
        ])->save();

        $auditLogger->record(
            'operations.expected_daily_site_report.excused',
            $expectedDailySiteReport,
            $actor,
            $oldValues,
            $expectedDailySiteReport->only(['status', 'excuse_reason', 'marked_by', 'marked_at']),
            $reason,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporting obligation excused.']);

        return back();
    }
}
