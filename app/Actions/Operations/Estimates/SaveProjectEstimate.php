<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Enums\ProjectEstimateStatus;
use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type EstimateResourcePayload array{resource_type: string, inventory_item_id?: string|null, unit_of_measure_id?: string|null, name: string, quantity_per_work_unit: numeric-string, estimated_unit_cost?: numeric-string|null, notes?: string|null}
 * @phpstan-type EstimateLinePayload array{work_item_key?: string|null, site_id?: string|null, unit_of_measure_id: string, boq_reference?: string|null, code?: string|null, name: string, planned_quantity: numeric-string, selling_rate?: numeric-string|null, estimated_unit_cost?: numeric-string|null, notes?: string|null, resources?: list<EstimateResourcePayload>}
 * @phpstan-type ProjectEstimatePayload array{title: string, currency_code: string, notes?: string|null, lines: list<EstimateLinePayload>}
 */
final readonly class SaveProjectEstimate
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /** @param ProjectEstimatePayload $data */
    public function handle(Project $project, array $data, User $actor, ?ProjectEstimate $estimate = null): ProjectEstimate
    {
        return DB::transaction(function () use ($actor, $data, $estimate, $project): ProjectEstimate {
            if ($estimate instanceof ProjectEstimate && ! $estimate->isDraft()) {
                throw ValidationException::withMessages(['estimate' => 'Only a draft estimate can be changed.']);
            }

            $oldValues = $estimate instanceof ProjectEstimate
                ? $estimate->load('lines.resources')->toArray()
                : [];

            if (! $estimate instanceof ProjectEstimate) {
                $version = (int) ProjectEstimate::query()
                    ->where('project_id', $project->id)
                    ->withTrashed()
                    ->lockForUpdate()
                    ->max('version_number') + 1;

                $estimate = ProjectEstimate::query()->create([
                    'tenant_id' => $project->tenant_id,
                    'branch_id' => $project->branch_id,
                    'project_id' => $project->id,
                    'version_number' => $version,
                    'title' => $data['title'],
                    'currency_code' => $data['currency_code'],
                    'status' => ProjectEstimateStatus::Draft,
                    'is_baseline' => false,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            } else {
                $estimate->update([
                    'title' => $data['title'],
                    'currency_code' => $data['currency_code'],
                    'notes' => $data['notes'] ?? null,
                    'updated_by' => $actor->id,
                ]);
            }

            $workItemKeys = [];
            foreach ($data['lines'] as $index => $lineData) {
                $workItemKey = $lineData['work_item_key'] ?? null;
                $workItemKey = is_string($workItemKey) ? $workItemKey : Str::uuid()->toString();
                $workItemKeys[] = $workItemKey;

                $this->assertSiteBelongsToProject($lineData['site_id'] ?? null, $project, $index);

                $line = $estimate->lines()->where('work_item_key', $workItemKey)->first();
                $attributes = [
                    'tenant_id' => $project->tenant_id,
                    'site_id' => $lineData['site_id'] ?? null,
                    'unit_of_measure_id' => $lineData['unit_of_measure_id'],
                    'work_item_key' => $workItemKey,
                    'boq_reference' => $lineData['boq_reference'] ?? null,
                    'code' => $lineData['code'] ?? null,
                    'name' => $lineData['name'],
                    'planned_quantity' => $lineData['planned_quantity'],
                    'selling_rate' => $lineData['selling_rate'] ?? null,
                    'estimated_unit_cost' => $lineData['estimated_unit_cost'] ?? null,
                    'sort_order' => $index,
                    'notes' => $lineData['notes'] ?? null,
                ];

                if ($line instanceof ProjectEstimateLine) {
                    $line->update($attributes);
                } else {
                    $line = $estimate->lines()->create($attributes);
                }

                $line->resources()->delete();
                foreach ($lineData['resources'] ?? [] as $resourceIndex => $resource) {
                    $line->resources()->create([
                        'tenant_id' => $project->tenant_id,
                        'resource_type' => $resource['resource_type'],
                        'inventory_item_id' => $resource['inventory_item_id'] ?? null,
                        'unit_of_measure_id' => $resource['unit_of_measure_id'] ?? null,
                        'name' => $resource['name'],
                        'quantity_per_work_unit' => $resource['quantity_per_work_unit'],
                        'estimated_unit_cost' => $resource['estimated_unit_cost'] ?? null,
                        'notes' => $resource['notes'] ?? null,
                        'sort_order' => $resourceIndex,
                    ]);
                }
            }

            $estimate->lines()->whereNotIn('work_item_key', $workItemKeys)->delete();
            $estimate->load('lines.resources');

            $this->auditLogger->record(
                $oldValues === [] ? 'operations.project_estimate.created' : 'operations.project_estimate.updated',
                $estimate,
                $actor,
                $oldValues,
                $estimate->toArray(),
                branch: $project->branch,
            );

            return $estimate;
        });
    }

    private function assertSiteBelongsToProject(?string $siteId, Project $project, int $index): void
    {
        if ($siteId === null) {
            return;
        }

        $belongs = Site::query()->whereKey($siteId)->where('project_id', $project->id)->exists();
        if (! $belongs) {
            throw ValidationException::withMessages([sprintf('lines.%d.site_id', $index) => 'The site must belong to this project.']);
        }
    }
}
