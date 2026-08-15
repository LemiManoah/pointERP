<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\DailySiteReportReview;
use App\Models\ExpectedDailySiteReport;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySiteReportNotificationService;
use Illuminate\Support\Facades\DB;

final readonly class ApproveDailySiteReport
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DailySiteReportNotificationService $notificationService,
    ) {
        //
    }

    public function handle(DailySiteReport $report, User $actor): DailySiteReport
    {
        return DB::transaction(function () use ($actor, $report): DailySiteReport {
            $report->loadMissing('workLines');
            $oldValues = $report->only(['status', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at']);

            $report->forceFill([
                'status' => DailySiteReport::STATUS_APPROVED,
                'reviewed_by' => $report->reviewed_by ?? $actor->id,
                'reviewed_at' => $report->reviewed_at ?? now(),
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            DailySiteReportReview::query()->create([
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'daily_site_report_id' => $report->id,
                'reviewed_by' => $actor->id,
                'action' => DailySiteReportReview::ACTION_APPROVED,
                'remarks' => null,
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

            $this->syncActivityQuantities($report);

            $this->auditLogger->record('operations.daily_site_report.approved', $report, $actor, $oldValues, $report->only(['status', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at']));
            DB::afterCommit(fn () => $this->notificationService->approved($report));

            return $report;
        });
    }

    private function syncActivityQuantities(DailySiteReport $report): void
    {
        foreach ($report->workLines as $line) {
            $activity = ProjectActivity::query()
                ->where('tenant_id', $report->tenant_id)
                ->where('project_id', $report->project_id)
                ->when($line->project_activity_id, fn ($query) => $query->whereKey($line->project_activity_id))
                ->when(! $line->project_activity_id && $line->boq_item_number, fn ($query) => $query->where('boq_item_number', $line->boq_item_number))
                ->first();

            if (! $activity instanceof ProjectActivity) {
                continue;
            }

            $approvedQuantity = (float) $activity->approved_quantity + (float) $line->quantity;
            $activity->forceFill(['approved_quantity' => $approvedQuantity])->save();
        }
    }
}
