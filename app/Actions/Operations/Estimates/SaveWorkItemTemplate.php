<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Enums\EstimateResourceType;
use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkItemResourceTemplate;
use App\Models\WorkItemTemplate;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type WorkItemResourceTemplatePayload array{resource_type: string, inventory_item_id?: string|null, unit_of_measure_id?: string|null, name: string, quantity_per_work_unit: numeric-string, unit_cost?: numeric-string|null, notes?: string|null}
 * @phpstan-type WorkItemTemplatePayload array{code?: string|null, category: string, name: string, unit_of_measure_id: string, default_selling_rate?: numeric-string|null, default_unit_cost?: numeric-string|null, specifications?: string|null, is_active?: bool, resources?: list<WorkItemResourceTemplatePayload>}
 */
final readonly class SaveWorkItemTemplate
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /** @param WorkItemTemplatePayload $data */
    public function handle(Tenant $tenant, array $data, User $actor, ?WorkItemTemplate $template = null): WorkItemTemplate
    {
        return DB::transaction(function () use ($actor, $data, $template, $tenant): WorkItemTemplate {
            $oldValues = $template instanceof WorkItemTemplate
                ? $template->load('resources')->toArray()
                : [];

            $resourcesData = $data['resources'] ?? [];

            // Dynamically calculate unit cost from resources
            $inventoryItemIds = collect($resourcesData)
                ->pluck('inventory_item_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            /** @var array<string, float> $itemCosts */
            $itemCosts = InventoryItem::query()
                ->whereIn('id', $inventoryItemIds)
                ->pluck('default_unit_cost', 'id')
                ->map(fn ($cost) => (float) ($cost ?? 0.0))
                ->all();

            $calculatedUnitCost = 0.0;
            $hasResourcesWithCost = false;

            foreach ($resourcesData as $res) {
                $qty = (float) $res['quantity_per_work_unit'];
                $cost = null;

                if (isset($res['unit_cost'])) {
                    $cost = (float) $res['unit_cost'];
                } elseif (! empty($res['inventory_item_id']) && isset($itemCosts[$res['inventory_item_id']])) {
                    $cost = $itemCosts[$res['inventory_item_id']];
                }

                if ($cost !== null) {
                    $calculatedUnitCost += ($qty * $cost);
                    $hasResourcesWithCost = true;
                }
            }

            $finalUnitCost = $hasResourcesWithCost
                ? (string) round($calculatedUnitCost, 4)
                : ($data['default_unit_cost'] ?? null);

            $attributes = [
                'tenant_id' => $tenant->id,
                'code' => $data['code'] ?? null,
                'category' => $data['category'],
                'name' => $data['name'],
                'unit_of_measure_id' => $data['unit_of_measure_id'],
                'default_selling_rate' => $data['default_selling_rate'] ?? null,
                'default_unit_cost' => $finalUnitCost,
                'specifications' => $data['specifications'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'updated_by' => $actor->id,
            ];

            if (! $template instanceof WorkItemTemplate) {
                $attributes['created_by'] = $actor->id;
                $template = WorkItemTemplate::query()->create($attributes);
            } else {
                $template->update($attributes);
            }

            // Sync resources
            $template->resources()->delete();

            foreach ($resourcesData as $index => $resource) {
                $type = EstimateResourceType::tryFrom($resource['resource_type']);
                if (! $type instanceof EstimateResourceType) {
                    throw ValidationException::withMessages([
                        sprintf('resources.%d.resource_type', $index) => 'Invalid resource type.',
                    ]);
                }

                $resourceCost = isset($resource['unit_cost'])
                    ? (string) $resource['unit_cost']
                    : (! empty($resource['inventory_item_id']) && isset($itemCosts[$resource['inventory_item_id']])
                        ? (string) $itemCosts[$resource['inventory_item_id']]
                        : null);

                WorkItemResourceTemplate::query()->create([
                    'tenant_id' => $tenant->id,
                    'work_item_template_id' => $template->id,
                    'resource_type' => $type,
                    'inventory_item_id' => $resource['inventory_item_id'] ?? null,
                    'unit_of_measure_id' => $resource['unit_of_measure_id'] ?? null,
                    'name' => $resource['name'],
                    'quantity_per_work_unit' => (string) $resource['quantity_per_work_unit'],
                    'unit_cost' => $resourceCost,
                    'notes' => $resource['notes'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            $template->load('resources.unit', 'resources.inventoryItem', 'unit');

            $this->auditLogger->record(
                $oldValues === [] ? 'operations.work_item_template.created' : 'operations.work_item_template.updated',
                $template,
                $actor,
                $oldValues,
                $template->toArray(),
            );

            return $template;
        });
    }
}
