<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Actions\Operations\Boq\ApplyProgressCorrection;
use App\Actions\Operations\Boq\ValidateDayworkUsage;
use App\Models\Project;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySiteReportNotificationService;
use App\Services\RefreshDailySiteReportCosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ApproveDailySiteReportCorrection
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DailySiteReportNotificationService $notificationService,
        private ApplyDsrEquipmentLineAdjustments $applyEquipmentAdjustments,
        private ApplyProgressCorrection $applyProgressCorrection,
        private ValidateDayworkUsage $validateDayworkUsage,
        private RefreshDailySiteReportCosts $refreshCosts,
    ) {
        //
    }

    public function handle(DailySiteReportCorrection $correction, User $actor): DailySiteReportCorrection
    {
        return DB::transaction(function () use ($actor, $correction): DailySiteReportCorrection {
            $correction->loadMissing('report');
            Project::query()->whereKey($correction->report->project_id)->lockForUpdate()->firstOrFail();
            $report = DailySiteReport::query()->whereKey($correction->daily_site_report_id)->lockForUpdate()->firstOrFail();
            $correction = DailySiteReportCorrection::query()->whereKey($correction->id)->lockForUpdate()->firstOrFail();

            if ($correction->status !== DailySiteReportCorrection::STATUS_SUBMITTED || ! $report->isApproved()) {
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
            $this->applyProgressCorrection->handle($correction, $report, $actor, ($correction->new_values ?? [])['work_adjustments'] ?? []);
            $treatments = ($correction->new_values ?? [])['usage_treatments'] ?? [];
            foreach ($treatments as $treatment) {
                if (! in_array($treatment['group'] ?? null, ['labour', 'equipment', 'material'], true)
                    || ! in_array($treatment['work_type'] ?? null, ['ordinary', 'daywork'], true)) {
                    throw ValidationException::withMessages(['changes.usage_treatments' => 'Invalid resource usage treatment.']);
                }
                $relation = $treatment['group'].'Lines';
                $line = $report->{$relation}()->whereKey($treatment['line_id'])->lockForUpdate()->first();
                if (! $line || $line->getAttribute('work_type') !== ($treatment['previous_type'] ?? null)) {
                    throw ValidationException::withMessages(['changes.usage_treatments' => 'Usage changed after this correction was requested. Review it again.']);
                }
                $line->update(['work_type' => $treatment['work_type']]);
            }
            if ($treatments !== []) {
                $this->validateDayworkUsage->handle($report);
            }

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

            $this->refreshCosts->handle($report);

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
