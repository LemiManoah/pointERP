<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\User;
use App\Services\AuditLogger;

final readonly class SubmitDailySiteReport
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(DailySiteReport $report, User $actor): DailySiteReport
    {
        $oldValues = $report->only(['status', 'submitted_by', 'submitted_at']);

        $report->forceFill([
            'status' => DailySiteReport::STATUS_SUBMITTED,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'updated_by' => $actor->id,
            'return_reason' => null,
            'returned_by' => null,
            'returned_at' => null,
        ])->save();

        $this->auditLogger->record('operations.daily_site_report.submitted', $report, $actor, $oldValues, $report->only(['status', 'submitted_by', 'submitted_at']));

        return $report;
    }
}
