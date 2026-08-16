<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReportCorrection;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySiteReportNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ApproveDailySiteReportCorrection
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DailySiteReportNotificationService $notificationService,
        private ApplyDsrEquipmentLineAdjustments $applyEquipmentAdjustments,
    ) {
        //
    }

    public function handle(DailySiteReportCorrection $correction, User $actor): DailySiteReportCorrection
    {
        return DB::transaction(function () use ($actor, $correction): DailySiteReportCorrection {
            $correction->loadMissing('report');
            $report = $correction->report;

            if ($correction->status !== DailySiteReportCorrection::STATUS_SUBMITTED || $report === null) {
                throw ValidationException::withMessages([
                    'correction' => 'Only a pending correction can be approved.',
                ]);
            }

            $allowedFields = [
                'weather',
                'site_conditions',
                'work_summary',
                'delay_summary',
                'visitor_summary',
                'hse_notes',
                'environment_notes',
                'social_notes',
                'completion_percent',
            ];
            $newValues = collect($correction->new_values ?? [])->only($allowedFields)->all();
            $oldValues = $report->only(array_keys($newValues));
            $equipmentAdjustments = ($correction->new_values ?? [])['equipment_adjustments'] ?? [];

            if (is_array($equipmentAdjustments) && $equipmentAdjustments !== []) {
                /** @var list<array<string, mixed>> $equipmentAdjustments */
                $this->applyEquipmentAdjustments->handle($correction, $report, $actor, $equipmentAdjustments);
            }

            $report->forceFill($newValues)->save();
            $correction->forceFill([
                'approved_by' => $actor->id,
                'status' => DailySiteReportCorrection::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
            ])->save();

            $this->auditLogger->record(
                'operations.daily_site_report.correction_approved',
                $report,
                $actor,
                $oldValues,
                $newValues,
                $correction->reason,
            );
            DB::afterCommit(fn () => $this->notificationService->correctionDecided($correction));

            return $correction;
        });
    }
}
