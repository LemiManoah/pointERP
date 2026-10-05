<?php

declare(strict_types=1);

namespace App\Actions\Operations\ProjectActivities;

use App\Models\DailySiteReportWorkLine;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimateLine;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type ProjectActivityPayload array{project_id: string, boq_item_id?: string|null, progress_method?: string, site_id?: string|null, code?: string|null, boq_item_number?: string|null, name: string, unit?: string|null, planned_quantity?: string|null, approved_quantity?: string|null, rate_amount?: string|null, currency_code?: string|null, status: string, sort_order?: int|string|null} */
final readonly class SaveProjectActivity
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /** @param ProjectActivityPayload $data */
    public function handle(array $data, User $actor, ?ProjectActivity $projectActivity = null): ProjectActivity
    {
        return DB::transaction(function () use ($data, $actor, $projectActivity): ProjectActivity {
            $project = Project::query()->whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            if ($projectActivity && $projectActivity->project_id !== $project->id) {
                throw ValidationException::withMessages(['project_id' => 'An activity cannot be moved to another project.']);
            }

            if (($data['site_id'] ?? null) !== null) {
                $site = Site::query()->whereKey($data['site_id'])->firstOrFail();

                if ($site->project_id !== $project->id) {
                    throw ValidationException::withMessages(['site_id' => 'The selected site does not belong to this project.']);
                }
            }

            $boqId = $data['boq_item_id'] ?? $projectActivity?->boq_item_id;
            $method = $data['progress_method'] ?? ($projectActivity instanceof ProjectActivity ? $projectActivity->progress_method : 'measured');
            if ($projectActivity?->estimate_work_item_key && $method !== 'measured') {
                throw ValidationException::withMessages(['progress_method' => 'The default BoQ activity must remain measured. Add a separate supporting activity.']);
            }

            if ($projectActivity && DailySiteReportWorkLine::query()->where('project_activity_id', $projectActivity->id)->exists()
                && ($data['unit'] ?? $projectActivity->unit) !== $projectActivity->unit) {
                throw ValidationException::withMessages(['unit' => 'A reported activity cannot change unit.']);
            }

            $baselineLine = null;
            if ($boqId) {
                $baselineLine = ProjectEstimateLine::query()->with(['unit', 'estimate'])
                    ->where('boq_item_id', $boqId)->whereHas('estimate', fn ($query) => $query
                    ->where('project_id', $project->id)->where('is_baseline', true))->first();
                if (! $baselineLine || $baselineLine->item_type->value !== 'measured') {
                    throw ValidationException::withMessages(['boq_item_id' => 'Select a measured item in this project’s approved baseline.']);
                }

                if ($projectActivity && DailySiteReportWorkLine::query()->where('project_activity_id', $projectActivity->id)->exists()
                    && ($boqId !== $projectActivity->boq_item_id || $method !== $projectActivity->progress_method)) {
                    throw ValidationException::withMessages(['boq_item_id' => 'A reported activity cannot change its BoQ link or measurement method. Add a new activity instead.']);
                }

                if ($baselineLine->site_id && ($data['site_id'] ?? null) !== $baselineLine->site_id) {
                    throw ValidationException::withMessages(['site_id' => 'Use the site assigned to this BoQ item.']);
                }
            }

            $attributes = [
                'tenant_id' => $project->tenant_id,
                'branch_id' => $project->branch_id,
                'project_id' => $project->id,
                'site_id' => $data['site_id'] ?? null,
                'code' => $data['code'] ?? null,
                'boq_item_number' => $data['boq_item_number'] ?? null,
                'name' => $data['name'],
                'unit' => $data['unit'] ?? null,
                'planned_quantity' => $data['planned_quantity'] ?? null,
                'approved_quantity' => $projectActivity instanceof ProjectActivity ? $projectActivity->approved_quantity : '0',
                'rate_amount' => $data['rate_amount'] ?? null,
                'currency_code' => $data['currency_code'] ?? null,
                'status' => $data['status'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'updated_by' => $actor->id,
            ];

            if ($baselineLine) {
                $attributes = [...$attributes,
                    'boq_item_id' => $boqId, 'progress_method' => $method,
                    'estimate_line_id' => $baselineLine->id,
                    'boq_item_number' => $baselineLine->boq_reference,
                    'unit' => $method === 'measured' ? ($baselineLine->unit->symbol ?? $baselineLine->unit->code) : $attributes['unit'],
                    'rate_amount' => $method === 'measured' ? $baselineLine->selling_rate : null,
                    'currency_code' => $baselineLine->estimate->currency_code,
                    'planned_quantity' => $projectActivity?->estimate_work_item_key ? $baselineLine->planned_quantity : null,
                ];
            }

            $oldValues = $projectActivity instanceof ProjectActivity ? $projectActivity->only(array_keys($attributes)) : [];

            if ($projectActivity instanceof ProjectActivity) {
                $projectActivity->update($attributes);
                $event = 'operations.project_activity.updated';
            } else {
                $projectActivity = ProjectActivity::query()->create([...$attributes, 'created_by' => $actor->id]);
                $event = 'operations.project_activity.created';
            }

            $this->auditLogger->record($event, $projectActivity, $actor, $oldValues, $projectActivity->fresh()?->toArray() ?? []);

            return $projectActivity;
        });
    }
}
