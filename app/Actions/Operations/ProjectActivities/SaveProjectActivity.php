<?php

declare(strict_types=1);

namespace App\Actions\Operations\ProjectActivities;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

final readonly class SaveProjectActivity
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /**
     * @param  array{project_id: string, site_id?: string|null, code?: string|null, boq_item_number?: string|null, name: string, unit?: string|null, planned_quantity?: string|null, approved_quantity?: string|null, rate_amount?: string|null, currency_code?: string|null, status: string, sort_order?: int|string|null}  $data
     */
    public function handle(array $data, User $actor, ?ProjectActivity $projectActivity = null): ProjectActivity
    {
        $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

        if (($data['site_id'] ?? null) !== null) {
            $site = Site::query()->whereKey($data['site_id'])->firstOrFail();

            if ($site->project_id !== $project->id) {
                throw ValidationException::withMessages(['site_id' => 'The selected site does not belong to this project.']);
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
            'approved_quantity' => $data['approved_quantity'] ?? '0',
            'rate_amount' => $data['rate_amount'] ?? null,
            'currency_code' => $data['currency_code'] ?? null,
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_by' => $actor->id,
        ];

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
    }
}
