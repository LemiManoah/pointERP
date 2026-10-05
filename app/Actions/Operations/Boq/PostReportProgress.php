<?php

declare(strict_types=1);

namespace App\Actions\Operations\Boq;

use App\Models\BoqProgressEntry;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PostReportProgress
{
    /**
     * Execute the action.
     */
    public function handle(DailySiteReport $report, User $actor): void
    {
        DB::transaction(function () use ($report, $actor): void {
            Project::query()->whereKey($report->project_id)->lockForUpdate()->firstOrFail();
            $report = DailySiteReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($report->status !== DailySiteReport::STATUS_APPROVED) {
                throw ValidationException::withMessages(['report' => 'Only approved reports contribute to progress.']);
            }

            foreach ($report->workLines as $line) {
                if (! $line->boq_item_id) {
                    continue;
                }

                if (! $line->counts_towards_boq) {
                    continue;
                }

                $activity = ProjectActivity::query()->whereKey($line->project_activity_id)
                    ->where('project_id', $report->project_id)->where('boq_item_id', $line->boq_item_id)
                    ->where('progress_method', 'measured')
                    ->lockForUpdate()->firstOrFail();
                if ($line->unit !== $activity->unit || (float) $line->quantity < 0) {
                    throw ValidationException::withMessages(['work_lines' => 'The reported quantity or unit is incompatible with the BoQ activity.']);
                }

                BoqProgressEntry::query()->firstOrCreate(['source_key' => 'dsr:'.$line->id], [
                    'tenant_id' => $report->tenant_id,
                    'project_id' => $report->project_id,
                    'boq_item_id' => $line->boq_item_id,
                    'project_activity_id' => $activity->id,
                    'estimate_line_id' => $activity->estimate_line_id,
                    'daily_site_report_work_line_id' => $line->id,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'measurement_date' => $report->report_date,
                    'approved_by' => $actor->id,
                    'description' => $line->description,
                ]);
                $activity->update(['approved_quantity' => BoqProgressEntry::query()
                    ->where('project_activity_id', $activity->id)->sum('quantity')]);
            }
        });
    }
}
