<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\User;
use App\Services\AuditLogger;

final readonly class ReturnDailySiteReport
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(DailySiteReport $report, User $actor, string $reason): DailySiteReport
    {
        $oldValues = $report->only(['status', 'returned_by', 'returned_at', 'return_reason']);

        $report->forceFill([
            'status' => DailySiteReport::STATUS_RETURNED,
            'returned_by' => $actor->id,
            'returned_at' => now(),
            'return_reason' => $reason,
            'updated_by' => $actor->id,
        ])->save();

        $this->auditLogger->record('operations.daily_site_report.returned', $report, $actor, $oldValues, $report->only(['status', 'returned_by', 'returned_at', 'return_reason']), $reason);

        return $report;
    }
}
