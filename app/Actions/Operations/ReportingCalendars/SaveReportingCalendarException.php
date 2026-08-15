<?php

declare(strict_types=1);

namespace App\Actions\Operations\ReportingCalendars;

use App\Models\ReportingCalendar;
use App\Models\ReportingCalendarException;
use App\Models\User;
use App\Services\AuditLogger;

final readonly class SaveReportingCalendarException
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /** @param array<string, mixed> $data */
    public function handle(ReportingCalendar $calendar, array $data, User $actor): ReportingCalendarException
    {
        $exception = ReportingCalendarException::query()->updateOrCreate(
            [
                'reporting_calendar_id' => $calendar->id,
                'exception_date' => $data['exception_date'],
            ],
            [
                ...$data,
                'tenant_id' => $calendar->tenant_id,
                'branch_id' => $calendar->branch_id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $this->auditLogger->record('operations.reporting_calendar_exception.created', $exception, $actor);

        return $exception;
    }
}
