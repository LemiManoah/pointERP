<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\DsrLabourAttendanceStatus;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final readonly class WorkforceExceptionReport
{
    public function __construct(
        private BranchContext $branchContext,
        private DsrLabourAttendanceComparison $comparison,
        private ReportingCalendarResolver $calendarResolver,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  array{project_id?: string|null, site_id?: string|null, date_from: string, date_to: string}  $filters
     * @return array{
     *   exceptions: list<array{
     *     id: string,
     *     type: string,
     *     type_label: string,
     *     severity: string,
     *     project_name: string,
     *     project_reference: string,
     *     site_name: string,
     *     date: string,
     *     summary: string,
     *     attended_hours: float|null,
     *     reported_hours: float|null,
     *     variance_hours: float|null,
     *     action_url: string
     *   }>,
     *   summary: array{total: int, unconfirmed: int, missing_attendance: int, labour_variance: int},
     *   projects: list<array{id: string, name: string, reference: string, sites: list<array{id: string, name: string}>}>
     * }
     */
    public function handle(User $actor, array $filters): array
    {
        $tenantId = $this->tenantContext->id();
        $branchIds = $this->branchContext->accessibleBranchIds($actor);
        $canViewAllBranches = $this->branchContext->canViewAllBranches($actor);
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        $projects = Project::query()
            ->with(['sites' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->unless($canViewAllBranches, fn (Builder $query): Builder => $query->whereIn('branch_id', $branchIds))
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $project): bool => Gate::forUser($actor)->allows('view', $project))
            ->values();

        $projectIds = $projects->pluck('id')->all();
        $selectedProjectId = $filters['project_id'] ?? null;
        $selectedSiteId = $filters['site_id'] ?? null;
        $rows = [];

        $registers = SiteAttendanceRegister::query()
            ->with(['project', 'site'])
            ->where('tenant_id', $tenantId)
            ->whereIn('project_id', $projectIds)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', AttendanceRegisterStatus::Draft->value)
            ->when($selectedProjectId, fn (Builder $query, string $id): Builder => $query->where('project_id', $id))
            ->when($selectedSiteId, fn (Builder $query, string $id): Builder => $query->where('site_id', $id))
            ->get();

        foreach ($registers as $register) {
            if (! Gate::forUser($actor)->allows('view', $register)) {
                continue;
            }
            if (! $register->site instanceof Site) {
                continue;
            }
            if ($this->calendarResolver->deadlineAt($register->site, $register->attendance_date)->isFuture()) {
                continue;
            }

            $relatedReport = DailySiteReport::query()
                ->where('site_id', $register->site_id)
                ->whereDate('report_date', $register->attendance_date->toDateString())
                ->whereIn('status', [
                    DailySiteReport::STATUS_SUBMITTED,
                    DailySiteReport::STATUS_REVIEWED,
                    DailySiteReport::STATUS_APPROVED,
                ])
                ->exists();
            $reopened = $register->reopened_at !== null && $relatedReport;

            $rows[] = [
                'id' => 'attendance:'.$register->id,
                'type' => $reopened ? 'attendance_reopened' : 'attendance_unconfirmed',
                'type_label' => $reopened ? 'Attendance reopened' : 'Attendance unconfirmed',
                'severity' => $reopened ? 'critical' : 'warning',
                'project_name' => $register->project->name,
                'project_reference' => $register->project->reference,
                'site_name' => $register->site->name,
                'date' => $register->attendance_date->toDateString(),
                'summary' => $reopened
                    ? 'Attendance was reopened after the related DSR entered review.'
                    : 'The attendance deadline passed while this register remained a draft.',
                'attended_hours' => null,
                'reported_hours' => null,
                'variance_hours' => null,
                'action_url' => '/workforce/attendance/'.$register->id,
            ];
        }

        $reports = DailySiteReport::query()
            ->with(['project', 'site'])
            ->where('tenant_id', $tenantId)
            ->whereIn('project_id', $projectIds)
            ->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', [
                DailySiteReport::STATUS_SUBMITTED,
                DailySiteReport::STATUS_REVIEWED,
                DailySiteReport::STATUS_APPROVED,
                DailySiteReport::STATUS_RETURNED,
            ])
            ->when($selectedProjectId, fn (Builder $query, string $id): Builder => $query->where('project_id', $id))
            ->when($selectedSiteId, fn (Builder $query, string $id): Builder => $query->where('site_id', $id))
            ->get();

        foreach ($reports as $report) {
            if (! Gate::forUser($actor)->allows('view', $report)) {
                continue;
            }

            $result = $this->comparisonResult($report);
            $status = DsrLabourAttendanceStatus::from($result['status']);

            if ($status === DsrLabourAttendanceStatus::Matched) {
                continue;
            }

            $rows[] = [
                'id' => 'dsr:'.$report->id,
                'type' => $status === DsrLabourAttendanceStatus::MissingAttendance ? 'missing_attendance' : 'labour_variance',
                'type_label' => $status->label(),
                'severity' => $status === DsrLabourAttendanceStatus::OverReported ? 'critical' : 'warning',
                'project_name' => $report->project->name,
                'project_reference' => $report->project->reference,
                'site_name' => $report->site->name,
                'date' => $report->report_date->toDateString(),
                'summary' => $this->summary($status, $result['variance_hours']),
                'attended_hours' => $result['attended_hours'],
                'reported_hours' => $result['reported_hours'],
                'variance_hours' => $result['variance_hours'],
                'action_url' => '/daily-site-reports/'.$report->id.'?tab=labour',
            ];
        }

        usort($rows, fn (array $left, array $right): int => [$right['date'], $right['severity']] <=> [$left['date'], $left['severity']]);

        return [
            'exceptions' => $rows,
            'summary' => [
                'total' => count($rows),
                'unconfirmed' => count(array_filter($rows, fn (array $row): bool => in_array($row['type'], ['attendance_unconfirmed', 'attendance_reopened'], true))),
                'missing_attendance' => count(array_filter($rows, fn (array $row): bool => $row['type'] === 'missing_attendance')),
                'labour_variance' => count(array_filter($rows, fn (array $row): bool => $row['type'] === 'labour_variance')),
            ],
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'reference' => $project->reference,
                'sites' => $project->sites->map(fn (Site $site): array => [
                    'id' => $site->id,
                    'name' => $site->name,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** @return array{status: string, attended_hours: float|null, reported_hours: float|null, variance_hours: float|null} */
    private function comparisonResult(DailySiteReport $report): array
    {
        if ($report->isApproved() && $report->labour_attendance_status instanceof DsrLabourAttendanceStatus) {
            $snapshot = $report->labour_attendance_snapshot ?? [];

            return [
                'status' => $report->labour_attendance_status->value,
                'attended_hours' => is_numeric($snapshot['attended_hours'] ?? null) ? (float) $snapshot['attended_hours'] : null,
                'reported_hours' => is_numeric($snapshot['reported_hours'] ?? null) ? (float) $snapshot['reported_hours'] : null,
                'variance_hours' => is_numeric($snapshot['variance_hours'] ?? null) ? (float) $snapshot['variance_hours'] : null,
            ];
        }

        $result = $this->comparison->compare($report);

        return [
            'status' => $result['status'],
            'attended_hours' => $result['attended_hours'],
            'reported_hours' => $result['reported_hours'],
            'variance_hours' => $result['variance_hours'],
        ];
    }

    private function summary(DsrLabourAttendanceStatus $status, ?float $variance): string
    {
        return match ($status) {
            DsrLabourAttendanceStatus::MissingAttendance => 'The DSR has labour entries but no confirmed attendance.',
            DsrLabourAttendanceStatus::OverReported => sprintf('Reported labour exceeds confirmed attendance by %s person-hours.', number_format(abs((float) $variance), 2)),
            DsrLabourAttendanceStatus::UnderReported => sprintf('%s attended person-hours have not been allocated to the DSR.', number_format(abs((float) $variance), 2)),
            DsrLabourAttendanceStatus::Matched => 'Attendance and reported labour agree.',
        };
    }
}
