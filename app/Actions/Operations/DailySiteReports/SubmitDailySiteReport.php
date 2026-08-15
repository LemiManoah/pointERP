<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\DailySiteReportReview;
use App\Models\DocumentLink;
use App\Models\ExpectedDailySiteReport;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySiteReportNotificationService;
use App\Services\ReportingCalendarResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SubmitDailySiteReport
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DailySiteReportNotificationService $notificationService,
        private ReportingCalendarResolver $calendarResolver,
    ) {
        //
    }

    public function handle(DailySiteReport $report, User $actor, ?string $evidenceOverrideReason = null): DailySiteReport
    {
        return DB::transaction(function () use ($actor, $evidenceOverrideReason, $report): DailySiteReport {
            $report->loadMissing(['workLines', 'site.project']);
            $this->validateCompleteness($report, $evidenceOverrideReason);

            $oldValues = $report->only(['status', 'submitted_by', 'submitted_at', 'return_reason']);
            $submittedAt = now();

            $report->forceFill([
                'status' => DailySiteReport::STATUS_SUBMITTED,
                'submitted_by' => $actor->id,
                'submitted_at' => $submittedAt,
                'updated_by' => $actor->id,
                'return_reason' => null,
                'returned_by' => null,
                'returned_at' => null,
            ])->save();

            DailySiteReportReview::query()->create([
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'daily_site_report_id' => $report->id,
                'reviewed_by' => $actor->id,
                'action' => DailySiteReportReview::ACTION_SUBMITTED,
                'remarks' => $evidenceOverrideReason,
            ]);

            $this->syncExpectedReport($report, $actor, $submittedAt);

            $this->auditLogger->record(
                'operations.daily_site_report.submitted',
                $report,
                $actor,
                $oldValues,
                $report->only(['status', 'submitted_by', 'submitted_at']),
                $evidenceOverrideReason,
            );

            DB::afterCommit(fn () => $this->notificationService->submitted($report));

            return $report;
        });
    }

    private function validateCompleteness(DailySiteReport $report, ?string $evidenceOverrideReason): void
    {
        if (blank($report->work_summary) && $report->workLines->isEmpty()) {
            throw ValidationException::withMessages([
                'work_summary' => 'Add a work summary or at least one work quantity line before submitting.',
            ]);
        }

        if ($report->workLines->isEmpty()) {
            return;
        }

        $hasEvidence = DocumentLink::query()
            ->where('linkable_type', $report::class)
            ->where('linkable_id', $report->id)
            ->exists();

        if (! $hasEvidence && blank($evidenceOverrideReason)) {
            throw ValidationException::withMessages([
                'evidence_override_reason' => 'Work quantities need linked evidence or an override reason.',
            ]);
        }
    }

    private function syncExpectedReport(DailySiteReport $report, User $actor, CarbonInterface $submittedAt): void
    {
        $expected = ExpectedDailySiteReport::query()->firstOrNew([
            'tenant_id' => $report->tenant_id,
            'site_id' => $report->site_id,
            'report_date' => $report->report_date->copy()->startOfDay(),
        ]);

        $expected->fill([
            'branch_id' => $report->branch_id,
            'project_id' => $report->project_id,
            'deadline_at' => $expected->deadline_at ?? $this->deadlineAt($report),
            'status' => $expected->deadline_at instanceof CarbonInterface && $submittedAt->greaterThan($expected->deadline_at)
                ? ExpectedDailySiteReport::STATUS_LATE
                : ExpectedDailySiteReport::STATUS_SUBMITTED,
            'daily_site_report_id' => $report->id,
            'submitted_at' => $submittedAt,
            'marked_by' => $actor->id,
            'marked_at' => now(),
        ])->save();
    }

    private function deadlineAt(DailySiteReport $report): CarbonInterface
    {
        $report->loadMissing('site.project');

        if ($report->site === null) {
            return $report->report_date->copy()->setTime(18, 0);
        }

        return $this->calendarResolver->deadlineAt($report->site, $report->report_date);
    }
}
