<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;

final readonly class SaveUnitOfMeasure
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?UnitOfMeasure $unit = null): UnitOfMeasure
    {
        $attributes = ['tenant_id' => $this->tenantContext->id(), 'code' => $data['code'], 'name' => $data['name'], 'symbol' => $data['symbol'] ?? null, 'quantity_dimension' => $data['quantity_dimension'], 'is_base_unit' => $data['is_base_unit'], 'is_active' => $data['is_active']];
        $old = $unit?->only(array_keys($attributes)) ?? [];
        if ($unit instanceof UnitOfMeasure) {
            $unit->update($attributes);
            $event = 'inventory.unit.updated';
        } else {
            $unit = UnitOfMeasure::query()->create($attributes);
            $event = 'inventory.unit.created';
        }

        $this->auditLogger->record($event, $unit, $actor, $old, $unit->fresh()?->toArray() ?? []);

        return $unit;
    }
}
