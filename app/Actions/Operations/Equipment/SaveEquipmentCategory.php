<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\EquipmentCategory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Str;

final readonly class SaveEquipmentCategory
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?EquipmentCategory $category = null): EquipmentCategory
    {
        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'code' => Str::upper((string) $data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'default_meter_type' => $data['default_meter_type'],
            'default_capacity_unit' => $data['default_capacity_unit'] ?? null,
            'fuel_efficiency_basis' => $data['fuel_efficiency_basis'] ?? null,
            'expected_fuel_efficiency' => $data['expected_fuel_efficiency'] ?? null,
            'fuel_tolerance_percent' => $data['fuel_tolerance_percent'] ?? null,
            'is_active' => $data['is_active'],
            'updated_by' => $actor->id,
        ];
        $oldValues = $category?->only(array_keys($attributes)) ?? [];
        if ($category instanceof EquipmentCategory) {
            $category->update($attributes);
            $event = 'equipment.category.updated';
        } else {
            $category = EquipmentCategory::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'equipment.category.created';
        }
        $this->auditLogger->record($event, $category, $actor, $oldValues, $category->fresh()?->toArray() ?? []);

        return $category;
    }
}
