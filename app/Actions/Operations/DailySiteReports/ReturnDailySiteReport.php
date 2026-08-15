<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\DailySiteReportReview;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySiteReportNotificationService;
use Illuminate\Support\Facades\DB;

final readonly class ReturnDailySiteReport
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DailySiteReportNotificationService $notificationService,
    ) {
        //
    }

    public function handle(DailySiteReport $report, User $actor, string $reason): DailySiteReport
    {
        return DB::transaction(function () use ($actor, $reason, $report): DailySiteReport {
            $oldValues = $report->only(['status', 'returned_by', 'returned_at', 'return_reason']);

            $report->forceFill([
                'status' => DailySiteReport::STATUS_RETURNED,
                'returned_by' => $actor->id,
                'returned_at' => now(),
                'return_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
                'updated_by' => $actor->id,
            ])->save();

            DailySiteReportReview::query()->create([
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'daily_site_report_id' => $report->id,
                'reviewed_by' => $actor->id,
                'action' => DailySiteReportReview::ACTION_RETURNED,
                'remarks' => $reason,
            ]);

            $this->auditLogger->record('operations.daily_site_report.returned', $report, $actor, $oldValues, $report->only(['status', 'returned_by', 'returned_at', 'return_reason']), $reason);
            DB::afterCommit(fn () => $this->notificationService->returned($report));

            return $report;
        });
    }
}
