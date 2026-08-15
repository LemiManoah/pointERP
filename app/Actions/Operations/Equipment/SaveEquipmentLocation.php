<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\EquipmentLocation;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveEquipmentLocation
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?EquipmentLocation $location = null): EquipmentLocation
    {
        $project = isset($data['project_id']) ? Project::query()->find($data['project_id']) : null;
        $site = isset($data['site_id']) ? Site::query()->find($data['site_id']) : null;

        if ($project instanceof Project && $project->branch_id !== $data['branch_id']) {
            throw ValidationException::withMessages(['project_id' => 'The project must belong to the selected branch.']);
        }

        if ($site instanceof Site && ($site->branch_id !== $data['branch_id'] || ($project instanceof Project && $site->project_id !== $project->id))) {
            throw ValidationException::withMessages(['site_id' => 'The site must belong to the selected branch and project.']);
        }

        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'branch_id' => $data['branch_id'],
            'project_id' => $data['project_id'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'type' => $data['type'],
            'code' => Str::upper((string) $data['code']),
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'is_active' => $data['is_active'],
            'updated_by' => $actor->id,
        ];
        $oldValues = $location?->only(array_keys($attributes)) ?? [];
        if ($location instanceof EquipmentLocation) {
            $location->update($attributes);
            $event = 'equipment.location.updated';
        } else {
            $location = EquipmentLocation::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'equipment.location.created';
        }
        $this->auditLogger->record($event, $location, $actor, $oldValues, $location->fresh()?->toArray() ?? []);

        return $location;
    }
}
