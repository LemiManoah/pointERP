<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Enums\BoqItemType;
use App\Enums\ProjectEstimateStatus;
use App\Models\BoqProgressEntry;
use App\Models\DailySiteReportWorkLine;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ApproveProjectEstimate
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(ProjectEstimate $estimate, User $actor): ProjectEstimate
    {
        return DB::transaction(function () use ($actor, $estimate): ProjectEstimate {
            Project::query()->whereKey($estimate->project_id)->lockForUpdate()->firstOrFail();
            $estimate = ProjectEstimate::query()->whereKey($estimate->id)->lockForUpdate()->firstOrFail();
            $estimate->load(['lines.unit', 'project.branch']);

            if (! $estimate->isDraft()) {
                throw ValidationException::withMessages(['estimate' => 'Only a draft estimate can become the baseline.']);
            }

            if ($estimate->lines->isEmpty()) {
                throw ValidationException::withMessages(['estimate' => 'Add at least one work item before approval.']);
            }

            $allowanceKeys = $estimate->lines
                ->filter(fn (ProjectEstimateLine $line): bool => $line->item_type !== BoqItemType::Measured)
                ->pluck('work_item_key');
            $changesReportedWork = $allowanceKeys->isNotEmpty() && ProjectActivity::query()
                ->where('project_id', $estimate->project_id)
                ->whereIn('estimate_work_item_key', $allowanceKeys)
                ->where(fn (Builder $query) => $query
                    ->where('approved_quantity', '!=', 0)
                    ->orWhereIn('id', DailySiteReportWorkLine::query()
                        ->where('tenant_id', $estimate->tenant_id)
                        ->whereNotNull('project_activity_id')
                        ->select('project_activity_id')))
                ->exists();

            if ($changesReportedWork) {
                throw ValidationException::withMessages(['estimate' => 'An item used in daily reports must remain measured work. Add a separate allowance item instead.']);
            }

            foreach ($estimate->lines as $line) {
                if ($line->item_type !== BoqItemType::Measured
                    && (DailySiteReportWorkLine::query()->where('boq_item_id', $line->boq_item_id)->exists()
                        || BoqProgressEntry::query()->where('boq_item_id', $line->boq_item_id)->exists())) {
                    throw ValidationException::withMessages(['estimate' => 'A reported BoQ item must remain measured. Create a separate allowance.']);
                }

                $previousLine = ProjectEstimateLine::query()->where('boq_item_id', $line->boq_item_id)
                    ->whereHas('estimate', fn (Builder $query) => $query->where('is_baseline', true))->first();
                if ($previousLine && $previousLine->unit_of_measure_id !== $line->unit_of_measure_id
                    && (DailySiteReportWorkLine::query()->where('boq_item_id', $line->boq_item_id)->exists()
                        || BoqProgressEntry::query()->where('boq_item_id', $line->boq_item_id)->exists())) {
                    throw ValidationException::withMessages(['estimate' => 'A reported BoQ item cannot change its measurement unit. Create a separate item for the new scope.']);
                }
            }

            $previousBaseline = ProjectEstimate::query()
                ->where('project_id', $estimate->project_id)
                ->where('is_baseline', true)
                ->whereKeyNot($estimate->id)
                ->lockForUpdate()
                ->first();

            if ($previousBaseline instanceof ProjectEstimate) {
                $previousBaseline->update([
                    'status' => ProjectEstimateStatus::Superseded,
                    'is_baseline' => false,
                    'updated_by' => $actor->id,
                ]);
            }

            $workLines = $estimate->lines->filter(fn (ProjectEstimateLine $line): bool => $line->item_type === BoqItemType::Measured);
            $activeKeys = $workLines->pluck('work_item_key')->all();
            ProjectActivity::query()->where('project_id', $estimate->project_id)
                ->whereNotNull('boq_item_id')->whereNotIn('boq_item_id', $workLines->pluck('boq_item_id'))
                ->update(['status' => 'inactive', 'updated_by' => $actor->id]);
            ProjectActivity::query()
                ->where('project_id', $estimate->project_id)
                ->whereNotNull('estimate_work_item_key')
                ->whereNotIn('estimate_work_item_key', $activeKeys)
                ->update(['status' => 'inactive', 'updated_by' => $actor->id]);

            foreach ($workLines as $line) {
                $this->syncWorkItem($estimate, $line, $actor);
            }

            $estimate->update([
                'status' => ProjectEstimateStatus::Approved,
                'is_baseline' => true,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $this->auditLogger->record(
                'operations.project_estimate.approved',
                $estimate,
                $actor,
                ['previous_baseline_id' => $previousBaseline?->id],
                ['baseline_id' => $estimate->id, 'version_number' => $estimate->version_number],
                'Approved as the project performance baseline.',
                $estimate->project->branch,
            );

            return $estimate->refresh();
        });
    }

    private function syncWorkItem(ProjectEstimate $estimate, ProjectEstimateLine $line, User $actor): void
    {
        $activity = ProjectActivity::query()
            ->where('project_id', $estimate->project_id)
            ->where('estimate_work_item_key', $line->work_item_key)
            ->first();

        $attributes = [
            'tenant_id' => $estimate->tenant_id,
            'branch_id' => $estimate->branch_id,
            'project_id' => $estimate->project_id,
            'site_id' => $line->site_id,
            'estimate_line_id' => $line->id,
            'boq_item_id' => $line->boq_item_id,
            'progress_method' => 'measured',
            'estimate_work_item_key' => $line->work_item_key,
            'code' => $line->code,
            'boq_item_number' => $line->boq_reference,
            'name' => $line->name,
            'unit' => $line->unit->symbol ?? $line->unit->code,
            'planned_quantity' => $line->planned_quantity,
            'rate_amount' => $line->selling_rate,
            'estimated_unit_cost' => $line->estimated_unit_cost,
            'currency_code' => $estimate->currency_code,
            'status' => 'active',
            'sort_order' => $line->sort_order,
            'updated_by' => $actor->id,
        ];

        ProjectActivity::query()->where('project_id', $estimate->project_id)
            ->where('boq_item_id', $line->boq_item_id)->whereNull('estimate_work_item_key')
            ->where('progress_method', 'measured')->update([
                'estimate_line_id' => $line->id, 'unit' => $attributes['unit'],
                'rate_amount' => $line->selling_rate, 'currency_code' => $estimate->currency_code,
                'planned_quantity' => null, 'updated_by' => $actor->id,
            ]);

        if ($activity instanceof ProjectActivity) {
            $activity->update($attributes);

            return;
        }

        ProjectActivity::query()->create([
            ...$attributes,
            'approved_quantity' => '0',
            'created_by' => $actor->id,
        ]);
    }
}
