<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Enums\ProjectEstimateStatus;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use App\Models\User;
use App\Services\AuditLogger;
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
            $estimate = ProjectEstimate::query()->whereKey($estimate->id)->lockForUpdate()->firstOrFail();
            $estimate->load(['lines.unit', 'project.branch']);

            if (! $estimate->isDraft()) {
                throw ValidationException::withMessages(['estimate' => 'Only a draft estimate can become the baseline.']);
            }

            if ($estimate->lines->isEmpty()) {
                throw ValidationException::withMessages(['estimate' => 'Add at least one work item before approval.']);
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

            $activeKeys = $estimate->lines->pluck('work_item_key')->all();
            ProjectActivity::query()
                ->where('project_id', $estimate->project_id)
                ->whereNotNull('estimate_work_item_key')
                ->whereNotIn('estimate_work_item_key', $activeKeys)
                ->update(['status' => 'inactive', 'updated_by' => $actor->id]);

            foreach ($estimate->lines as $line) {
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
