<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySiteReportNotificationService;
use Illuminate\Support\Facades\DB;

final readonly class CreateDailySiteReportCorrection
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DailySiteReportNotificationService $notificationService,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    public function handle(DailySiteReport $report, User $actor, string $reason, array $newValues): DailySiteReportCorrection
    {
        return DB::transaction(function () use ($actor, $newValues, $reason, $report): DailySiteReportCorrection {
            $reportValues = collect($newValues)->except('equipment_adjustments')->all();
            $oldValues = $report->only(array_keys($reportValues));
            if (isset($newValues['equipment_adjustments'])) {
                $oldValues['equipment_adjustments'] = [];
            }

            $correction = DailySiteReportCorrection::query()->create([
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'daily_site_report_id' => $report->id,
                'requested_by' => $actor->id,
                'status' => DailySiteReportCorrection::STATUS_SUBMITTED,
                'reason' => $reason,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);

            $this->auditLogger->record(
                'operations.daily_site_report.correction_requested',
                $report,
                $actor,
                $oldValues,
                $newValues,
                $reason,
            );
            DB::afterCommit(fn () => $this->notificationService->correctionRequested($correction));

            return $correction;
        });
    }
}
