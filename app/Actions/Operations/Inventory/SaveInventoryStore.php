<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\EquipmentLocation;
use App\Models\InventoryStore;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Validation\ValidationException;

final readonly class SaveInventoryStore
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?InventoryStore $store = null): InventoryStore
    {
        $project = isset($data['project_id']) ? Project::query()->find($data['project_id']) : null;
        $site = isset($data['site_id']) ? Site::query()->find($data['site_id']) : null;
        $location = isset($data['equipment_location_id']) ? EquipmentLocation::query()->find($data['equipment_location_id']) : null;
        if ($project instanceof Project && $project->branch_id !== $data['branch_id']) {
            throw ValidationException::withMessages(['project_id' => 'The project must belong to the selected branch.']);
        }

        if ($site instanceof Site && ($site->branch_id !== $data['branch_id'] || ($project instanceof Project && $site->project_id !== $project->id))) {
            throw ValidationException::withMessages(['site_id' => 'The selected site must belong to the selected project and branch.']);
        }

        if ($location instanceof EquipmentLocation && $location->branch_id !== $data['branch_id']) {
            throw ValidationException::withMessages(['equipment_location_id' => 'The equipment location must belong to the selected branch.']);
        }

        $attributes = ['tenant_id' => $this->tenantContext->id(), 'branch_id' => $data['branch_id'], 'equipment_location_id' => $data['equipment_location_id'] ?? null, 'project_id' => $data['project_id'] ?? null, 'site_id' => $data['site_id'] ?? null, 'code' => $data['code'], 'name' => $data['name'], 'type' => $data['type'], 'address' => $data['address'] ?? null, 'is_active' => $data['is_active'], 'updated_by' => $actor->id];
        $old = $store?->only(array_keys($attributes)) ?? [];
        if ($store instanceof InventoryStore) {
            $store->update($attributes);
            $event = 'inventory.store.updated';
        } else {
            $store = InventoryStore::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'inventory.store.created';
        }

        $this->auditLogger->record($event, $store, $actor, $old, $store->fresh()?->toArray() ?? []);

        return $store;
    }
}
