<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DsrLabourAttendanceStatus;
use App\Models\DailySiteReport;
use App\Models\Site;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final readonly class WorkforceExceptionNotificationService
{
    public function __construct(
        private DailySiteReportRecipientResolver $recipients,
        private DsrLabourAttendanceComparison $comparison,
        private OperationalNotificationSender $notifications,
    ) {}

    public function reportSubmitted(DailySiteReport $report): int
    {
        $result = $this->comparison->compare($report);
        $status = DsrLabourAttendanceStatus::from($result['status']);

        if (! in_array($status, [DsrLabourAttendanceStatus::MissingAttendance, DsrLabourAttendanceStatus::OverReported], true)) {
            return 0;
        }

        $missing = $status === DsrLabourAttendanceStatus::MissingAttendance;
        $key = 'workforce:dsr:'.$report->id.':'.$status->value;

        return $this->sendOnce(
            $this->recipients->reviewers($report),
            $key,
            [
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'project_id' => $report->project_id,
                'site_id' => $report->site_id,
                'daily_site_report_id' => $report->id,
                'category' => 'workforce_exception',
                'severity' => $missing ? 'warning' : 'critical',
                'title' => $missing ? 'DSR submitted without confirmed attendance' : 'DSR labour exceeds attendance',
                'message' => $missing
                    ? sprintf('%s has reported labour but no confirmed attendance.', $report->reference)
                    : sprintf('%s exceeds confirmed attendance by %s person-hours.', $report->reference, number_format(abs($result['variance_hours']), 2)),
                'action_url' => '/daily-site-reports/'.$report->id.'?tab=labour',
            ],
        );
    }

    public function attendanceOverdue(SiteAttendanceRegister $register): int
    {
        $register->loadMissing(['recorder', 'site.manager', 'site.project']);
        $site = $register->site;

        if (! $site instanceof Site) {
            return 0;
        }

        $recipients = $this->recipients->submitters($site);

        if ($register->recorder instanceof User && $register->recorder->is_active) {
            $recipients->push($register->recorder);
        }

        return $this->sendOnce(
            $recipients->unique('id')->values(),
            'workforce:attendance:'.$register->id.':unconfirmed',
            [
                'tenant_id' => $register->tenant_id,
                'branch_id' => $register->branch_id,
                'project_id' => $register->project_id,
                'site_id' => $register->site_id,
                'site_attendance_register_id' => $register->id,
                'category' => 'workforce_exception',
                'severity' => 'warning',
                'title' => 'Site attendance remains unconfirmed',
                'message' => sprintf('%s attendance for %s (%s shift) is still a draft.', $site->name, $register->attendance_date->toFormattedDateString(), $register->shift->label()),
                'action_url' => '/workforce/attendance/'.$register->id,
            ],
        );
    }

    public function attendanceReopened(SiteAttendanceRegister $register): int
    {
        $reports = DailySiteReport::query()
            ->with(['project.manager', 'project.users', 'site.manager', 'site.users'])
            ->where('site_id', $register->site_id)
            ->whereDate('report_date', $register->attendance_date->toDateString())
            ->whereIn('status', [
                DailySiteReport::STATUS_SUBMITTED,
                DailySiteReport::STATUS_REVIEWED,
                DailySiteReport::STATUS_APPROVED,
            ])
            ->get();
        $sent = 0;

        foreach ($reports as $report) {
            $recipients = $this->recipients->reviewers($report);

            if ($report->submittedBy instanceof User && $report->submittedBy->is_active) {
                $recipients->push($report->submittedBy);
            }

            $sent += $this->sendOnce(
                $recipients->unique('id')->values(),
                'workforce:attendance:'.$register->id.':reopened:'.$register->reopened_at?->getTimestamp().':'.$report->id,
                [
                    'tenant_id' => $register->tenant_id,
                    'branch_id' => $register->branch_id,
                    'project_id' => $register->project_id,
                    'site_id' => $register->site_id,
                    'site_attendance_register_id' => $register->id,
                    'daily_site_report_id' => $report->id,
                    'category' => 'workforce_exception',
                    'severity' => 'critical',
                    'title' => 'Attendance reopened after DSR review',
                    'message' => sprintf('Attendance supporting %s was reopened and must be checked again.', $report->reference),
                    'action_url' => '/daily-site-reports/'.$report->id.'?tab=labour',
                ],
            );
        }

        return $sent;
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $payload
     */
    private function sendOnce(Collection $recipients, string $key, array $payload): int
    {
        $pending = $recipients
            ->reject(fn (User $user): bool => $user->notifications()
                ->get()
                ->contains(fn (DatabaseNotification $notification): bool => ($notification->data['alert_key'] ?? null) === $key))
            ->values();

        if ($pending->isEmpty()) {
            return 0;
        }

        $this->notifications->send($pending, [...$payload, 'alert_key' => $key]);

        return $pending->count();
    }
}
