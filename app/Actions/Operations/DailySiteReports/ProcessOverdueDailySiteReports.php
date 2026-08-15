<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\ExpectedDailySiteReport;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\DailySiteReportRecipientResolver;
use App\Services\OperationalNotificationSender;
use App\Services\ReportingCalendarResolver;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;

final readonly class ProcessOverdueDailySiteReports
{
    public function __construct(
        private TenantContext $tenantContext,
        private ReportingCalendarResolver $calendarResolver,
        private DailySiteReportRecipientResolver $recipients,
        private OperationalNotificationSender $notifications,
        private AuditLogger $auditLogger,
    ) {
        //
    }

    /** @return array{missing: int, notified: int, escalated: int} */
    public function handle(CarbonImmutable $asOf, ?string $tenantId = null, ?string $siteId = null): array
    {
        $result = ['missing' => 0, 'notified' => 0, 'escalated' => 0];
        $tenants = Tenant::query()
            ->active()
            ->when($tenantId, fn ($query, string $id) => $query->whereKey($id))
            ->get();

        foreach ($tenants as $tenant) {
            $this->tenantContext->set($tenant);
            $expectedReports = ExpectedDailySiteReport::query()
                ->with(['report', 'site.manager', 'site.project.manager', 'site.project.users'])
                ->whereIn('status', [ExpectedDailySiteReport::STATUS_EXPECTED, ExpectedDailySiteReport::STATUS_LATE, ExpectedDailySiteReport::STATUS_MISSING])
                ->where('deadline_at', '<', $asOf)
                ->when($siteId, fn ($query, string $id) => $query->where('site_id', $id))
                ->oldest('report_date')
                ->get();

            foreach ($expectedReports as $expected) {
                $site = $expected->site;

                if (! $site instanceof Site) {
                    continue;
                }

                if ($expected->status !== ExpectedDailySiteReport::STATUS_MISSING) {
                    $this->markMissing($expected, $site);
                    $result['missing']++;
                }

                if ($expected->notified_at === null) {
                    $submitters = $this->recipients->submitters($site);

                    if ($submitters->isNotEmpty()) {
                        $this->notifications->send($submitters, $this->payload(
                            expected: $expected,
                            category: 'dsr_missing',
                            severity: 'warning',
                            title: 'Daily site report is missing',
                            message: sprintf('%s has no submitted DSR for %s.', $site->name, $expected->report_date->toFormattedDateString()),
                        ));
                        $expected->forceFill(['notified_at' => now()])->save();
                        $result['notified']++;
                        $this->auditLogger->record('operations.expected_dsr.notified', $expected);
                    }
                }

                if ($expected->escalated_at === null && $this->shouldEscalate($expected, $site)) {
                    $managers = $this->recipients->escalationRecipients($site);

                    if ($managers->isNotEmpty()) {
                        $days = $this->calendarResolver->escalationDays($site);
                        $this->notifications->send($managers, $this->payload(
                            expected: $expected,
                            category: 'dsr_escalation',
                            severity: 'critical',
                            title: 'Repeated missing site reports',
                            message: sprintf('%s has missed %d consecutive reporting days.', $site->name, $days),
                        ));
                        $expected->forceFill(['escalated_at' => now()])->save();
                        $result['escalated']++;
                        $this->auditLogger->record('operations.expected_dsr.escalated', $expected);
                    }
                }
            }
        }

        return $result;
    }

    private function markMissing(ExpectedDailySiteReport $expected, Site $site): void
    {
        $report = $expected->report;

        if (! $report instanceof DailySiteReport) {
            $report = DailySiteReport::query()->create([
                'tenant_id' => $expected->tenant_id,
                'branch_id' => $expected->branch_id,
                'project_id' => $expected->project_id,
                'site_id' => $expected->site_id,
                'report_date' => $expected->report_date,
                'reference' => 'DSR-'.$site->reference.'-'.$expected->report_date->format('Ymd'),
                'status' => DailySiteReport::STATUS_MISSING,
                'expected_at' => $expected->deadline_at,
            ]);
        } elseif ($report->isEditable()) {
            $report->forceFill(['status' => DailySiteReport::STATUS_MISSING])->save();
        }

        $expected->forceFill([
            'status' => ExpectedDailySiteReport::STATUS_MISSING,
            'daily_site_report_id' => $report->id,
            'marked_at' => now(),
        ])->save();

        $this->auditLogger->record('operations.expected_dsr.marked_missing', $expected);
    }

    private function shouldEscalate(ExpectedDailySiteReport $expected, Site $site): bool
    {
        $threshold = $this->calendarResolver->escalationDays($site);
        $recent = ExpectedDailySiteReport::query()
            ->where('site_id', $site->id)
            ->whereDate('report_date', '<=', $expected->report_date->toDateString())
            ->latest('report_date')
            ->limit($threshold)
            ->pluck('status');

        return $recent->count() === $threshold
            && $recent->every(fn (string $status): bool => $status === ExpectedDailySiteReport::STATUS_MISSING);
    }

    /** @return array<string, mixed> */
    private function payload(ExpectedDailySiteReport $expected, string $category, string $severity, string $title, string $message): array
    {
        return [
            'tenant_id' => $expected->tenant_id,
            'branch_id' => $expected->branch_id,
            'project_id' => $expected->project_id,
            'site_id' => $expected->site_id,
            'category' => $category,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => $expected->daily_site_report_id
                ? '/daily-site-reports/'.$expected->daily_site_report_id
                : '/daily-site-reports?status=missing',
        ];
    }
}
