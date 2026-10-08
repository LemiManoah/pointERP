<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Actions\Operations\Boq\PostReportProgress;
use App\Actions\Operations\Boq\ValidateDayworkUsage;
use App\Enums\DsrLabourAttendanceStatus;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportReview;
use App\Models\ExpectedDailySiteReport;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySiteReportNotificationService;
use App\Services\DsrLabourAttendanceComparison;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ApproveDailySiteReport
{
    public function __construct(
        private AuditLogger $auditLogger,
        private ValidateDayworkUsage $validateDayworkUsage,
        private DailySiteReportNotificationService $notificationService,
        private DsrLabourAttendanceComparison $labourComparison,
        private PostApprovedDsrEquipmentLines $postEquipmentLines,
        private PostApprovedDsrMaterialUsage $postMaterialUsage,
        private PostReportProgress $postProgress,
    ) {
        //
    }

    public function handle(DailySiteReport $report, User $actor, ?string $labourVarianceOverrideReason = null): DailySiteReport
    {
        return DB::transaction(function () use ($actor, $labourVarianceOverrideReason, $report): DailySiteReport {
            Project::query()->whereKey($report->project_id)->lockForUpdate()->firstOrFail();
            $report = DailySiteReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($report->status === DailySiteReport::STATUS_APPROVED) {
                return $report;
            }

            if (! in_array($report->status, [DailySiteReport::STATUS_SUBMITTED, DailySiteReport::STATUS_REVIEWED], true)) {
                throw ValidationException::withMessages(['report' => 'Submit the report before approving it.']);
            }

            $this->validateDayworkUsage->handle($report);
            $report->loadMissing('workLines');
            $labourComparison = $this->labourComparison->compare($report);
            $isOverReported = $labourComparison['status'] === DsrLabourAttendanceStatus::OverReported->value;
            $overrideReason = is_string($labourVarianceOverrideReason) && mb_trim($labourVarianceOverrideReason) !== ''
                ? mb_trim($labourVarianceOverrideReason)
                : null;

            if ($isOverReported && ! $actor->can('daily-site-reports.override-labour-variance')) {
                throw ValidationException::withMessages([
                    'labour_variance_override_reason' => 'Reported labour exceeds confirmed attendance. You do not have permission to override this variance.',
                ]);
            }

            if ($isOverReported && $overrideReason === null) {
                throw ValidationException::withMessages([
                    'labour_variance_override_reason' => 'Explain why the DSR labour exceeds confirmed attendance before approving.',
                ]);
            }

            $checkedAt = now();
            $labourSnapshot = [
                ...$labourComparison,
                'checked_at' => $checkedAt->toIso8601String(),
                'override_reason' => $isOverReported ? $overrideReason : null,
                'override_by' => $isOverReported ? $actor->id : null,
            ];
            $oldValues = $report->only(['status', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at']);

            $report->forceFill([
                'status' => DailySiteReport::STATUS_APPROVED,
                'reviewed_by' => $report->reviewed_by ?? $actor->id,
                'reviewed_at' => $report->reviewed_at ?? now(),
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'updated_by' => $actor->id,
                'labour_attendance_status' => $labourComparison['status'],
                'labour_attendance_snapshot' => $labourSnapshot,
                'labour_attendance_checked_at' => $checkedAt,
                'labour_attendance_override_reason' => $isOverReported ? $overrideReason : null,
                'labour_attendance_override_by' => $isOverReported ? $actor->id : null,
            ])->save();

            DailySiteReportReview::query()->create([
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'daily_site_report_id' => $report->id,
                'reviewed_by' => $actor->id,
                'action' => DailySiteReportReview::ACTION_APPROVED,
                'remarks' => $isOverReported ? $overrideReason : null,
            ]);

            ExpectedDailySiteReport::query()
                ->where('tenant_id', $report->tenant_id)
                ->where('site_id', $report->site_id)
                ->whereDate('report_date', $report->report_date->toDateString())
                ->update([
                    'status' => ExpectedDailySiteReport::STATUS_SUBMITTED,
                    'daily_site_report_id' => $report->id,
                    'submitted_at' => $report->submitted_at,
                    'marked_by' => $actor->id,
                    'marked_at' => now(),
                ]);

            $this->postProgress->handle($report, $actor);
            $this->syncActivityQuantities($report);
            $this->postEquipmentLines->handle($report, $actor);
            $this->postMaterialUsage->handle($report, $actor);

            $this->auditLogger->record('operations.daily_site_report.approved', $report, $actor, $oldValues, $report->only([
                'status', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at',
                'labour_attendance_status', 'labour_attendance_checked_at',
                'labour_attendance_override_reason', 'labour_attendance_override_by',
            ]));

            if ($isOverReported) {
                $this->auditLogger->record(
                    'operations.daily_site_report.labour_variance_overridden',
                    $report,
                    $actor,
                    [],
                    $labourSnapshot,
                    $overrideReason,
                    $report->branch,
                );
            }

            DB::afterCommit(fn () => $this->notificationService->approved($report));

            return $report;
        });
    }

    private function syncActivityQuantities(DailySiteReport $report): void
    {
        foreach ($report->workLines as $line) {
            if ($line->boq_item_id) {
                continue;
            }

            if (! $line->project_activity_id) {
                continue;
            }

            $activity = ProjectActivity::query()
                ->where('tenant_id', $report->tenant_id)
                ->where('project_id', $report->project_id)
                ->whereKey($line->project_activity_id)
                ->lockForUpdate()
                ->first();

            if (! $activity instanceof ProjectActivity) {
                continue;
            }

            $approvedQuantity = (string) BigDecimal::of($activity->approved_quantity ?? '0')->plus($line->quantity ?? '0');
            $activity->forceFill(['approved_quantity' => $approvedQuantity])->save();
        }
    }
}
