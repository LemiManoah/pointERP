<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\ReportingCalendars\SaveReportingCalendarException;
use App\Http\Requests\Operations\ReportingCalendars\StoreReportingCalendarExceptionRequest;
use App\Models\ReportingCalendar;
use App\Models\ReportingCalendarException;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ReportingCalendarExceptionController
{
    public function store(StoreReportingCalendarExceptionRequest $request, ReportingCalendar $reportingCalendar, SaveReportingCalendarException $action): RedirectResponse
    {
        Gate::authorize('update', $reportingCalendar);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($reportingCalendar, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calendar exception saved.']);

        return to_route('reporting-calendars.index');
    }

    public function destroy(ReportingCalendar $reportingCalendar, ReportingCalendarException $exception, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($exception->reporting_calendar_id === $reportingCalendar->id, 404);
        Gate::authorize('update', $reportingCalendar);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $auditLogger->record('operations.reporting_calendar_exception.deleted', $exception, $actor);
        $exception->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calendar exception removed.']);

        return to_route('reporting-calendars.index');
    }
}

