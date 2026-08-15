<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\ReportingCalendars\SaveReportingCalendar;
use App\Http\Requests\Operations\ReportingCalendars\StoreReportingCalendarRequest;
use App\Http\Requests\Operations\ReportingCalendars\UpdateReportingCalendarRequest;
use App\Models\Project;
use App\Models\ReportingCalendar;
use App\Models\ReportingCalendarException;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ReportingCalendarController
{
    public function index(BranchContext $branchContext): Response
    {
        Gate::authorize('viewAny', ReportingCalendar::class);
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $branchIds = $branchContext->accessibleBranchIds($user);

        $calendars = ReportingCalendar::query()
            ->with(['project', 'site', 'exceptions'])
            ->latest()
            ->get()
            ->filter(fn (ReportingCalendar $calendar): bool => Gate::forUser($user)->allows('view', $calendar))
            ->values()
            ->map(fn (ReportingCalendar $calendar): array => $this->calendarRow($calendar));

        return Inertia::render('operations/reporting-calendars/index', [
            'calendars' => $calendars,
            'projects' => Project::query()
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project))
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                ])
                ->values(),
            'sites' => Site::query()
                ->with('project')
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->filter(fn (Site $site): bool => Gate::forUser($user)->allows('view', $site))
                ->map(fn (Site $site): array => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'project_id' => $site->project_id,
                ])
                ->values(),
            'timezones' => [
                ['value' => 'Africa/Kampala', 'label' => 'Africa/Kampala'],
                ['value' => 'Africa/Juba', 'label' => 'Africa/Juba'],
                ['value' => 'UTC', 'label' => 'UTC'],
            ],
            'canManage' => Gate::forUser($user)->allows('create', ReportingCalendar::class),
        ]);
    }

    public function store(StoreReportingCalendarRequest $request, SaveReportingCalendar $action): RedirectResponse
    {
        Gate::authorize('create', ReportingCalendar::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporting calendar created.']);

        return to_route('reporting-calendars.index');
    }

    public function update(UpdateReportingCalendarRequest $request, ReportingCalendar $reportingCalendar, SaveReportingCalendar $action): RedirectResponse
    {
        Gate::authorize('update', $reportingCalendar);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($request->validated(), $actor, $reportingCalendar);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporting calendar updated.']);

        return to_route('reporting-calendars.index');
    }

    public function destroy(ReportingCalendar $reportingCalendar, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $reportingCalendar);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $reportingCalendar->forceFill(['is_active' => false, 'updated_by' => $actor->id])->save();
        $auditLogger->record('operations.reporting_calendar.archived', $reportingCalendar, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporting calendar moved to inactive.']);

        return to_route('reporting-calendars.index');
    }

    /** @return array<string, mixed> */
    private function calendarRow(ReportingCalendar $calendar): array
    {
        $projectName = $calendar->project_id !== null && $calendar->project instanceof Project
            ? $calendar->project->name
            : null;
        $siteName = $calendar->site_id !== null && $calendar->site instanceof Site
            ? $calendar->site->name
            : null;

        return [
            'id' => $calendar->id,
            'name' => $calendar->name,
            'project_id' => $calendar->project_id,
            'project_name' => $projectName,
            'site_id' => $calendar->site_id,
            'site_name' => $siteName,
            'scope' => $siteName ?? $projectName ?? 'Tenant default',
            'timezone' => $calendar->timezone,
            'reporting_deadline' => mb_substr($calendar->reporting_deadline, 0, 5),
            'working_days' => $calendar->working_days,
            'missing_escalation_days' => $calendar->missing_escalation_days,
            'is_active' => $calendar->is_active,
            'exceptions' => $calendar->exceptions
                ->sortBy('exception_date')
                ->values()
                ->map(function (ReportingCalendarException $exception): array {
                    $reason = $exception->getAttribute('reason');

                    return [
                        'id' => $exception->id,
                        'exception_date' => $exception->exception_date->toDateString(),
                        'type' => $exception->type,
                        'name' => $exception->name,
                        'reason' => is_string($reason) ? $reason : null,
                    ];
                })
                ->all(),
        ];
    }
}
