<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReportingCalendar;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class ReportingCalendarResolver
{
    public function __construct(private TenantContext $tenantContext)
    {
        //
    }

    public function calendarFor(Site $site): ?ReportingCalendar
    {
        $base = ReportingCalendar::query()
            ->with('exceptions')
            ->where('tenant_id', $site->tenant_id)
            ->where('is_active', true);

        $siteCalendar = (clone $base)->where('site_id', $site->id)->latest()->first();

        if ($siteCalendar instanceof ReportingCalendar) {
            return $siteCalendar;
        }

        $projectCalendar = (clone $base)
            ->where('project_id', $site->project_id)
            ->whereNull('site_id')
            ->latest()
            ->first();

        if ($projectCalendar instanceof ReportingCalendar) {
            return $projectCalendar;
        }

        return (clone $base)
            ->whereNull('project_id')
            ->whereNull('site_id')
            ->latest()
            ->first();
    }

    public function isReportingDay(Site $site, CarbonInterface $date): bool
    {
        $calendar = $this->calendarFor($site);

        if ($calendar instanceof ReportingCalendar) {
            return $calendar->isReportingDay($date);
        }

        return $date->dayOfWeekIso !== 7;
    }

    public function deadlineAt(Site $site, CarbonInterface $date): CarbonImmutable
    {
        $calendar = $this->calendarFor($site);
        $timezone = $calendar instanceof ReportingCalendar
            ? $calendar->timezone
            : $this->tenantContext->current()->timezone;
        $deadline = $calendar instanceof ReportingCalendar
            ? $calendar->reporting_deadline
            : ($site->reporting_deadline ?? $site->project->reporting_deadline ?? '18:00:00');
        [$hour, $minute, $second] = array_pad(explode(':', (string) $deadline), 3, '0');

        return CarbonImmutable::parse($date->toDateString(), $timezone)
            ->setTime((int) $hour, (int) $minute, (int) $second)
            ->utc();
    }

    public function escalationDays(Site $site): int
    {
        $calendar = $this->calendarFor($site);

        return max(1, $calendar instanceof ReportingCalendar ? $calendar->missing_escalation_days : 2);
    }
}
