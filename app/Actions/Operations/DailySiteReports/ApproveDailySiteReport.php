<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\User;
use App\Services\AuditLogger;

final readonly class ApproveDailySiteReport
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(DailySiteReport $report, User $actor): DailySiteReport
    {
        $oldValues = $report->only(['status', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at']);

        $report->forceFill([
            'status' => DailySiteReport::STATUS_APPROVED,
            'reviewed_by' => $report->reviewed_by ?? $actor->id,
            'reviewed_at' => $report->reviewed_at ?? now(),
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'updated_by' => $actor->id,
        ])->save();

        $this->auditLogger->record('operations.daily_site_report.approved', $report, $actor, $oldValues, $report->only(['status', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at']));

        return $report;
    }
}
