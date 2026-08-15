<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveEquipment
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?Equipment $equipment = null): Equipment
    {
        $location = isset($data['default_location_id']) ? EquipmentLocation::query()->find($data['default_location_id']) : null;

        if ($location instanceof EquipmentLocation && $location->branch_id !== $data['branch_id']) {
            throw ValidationException::withMessages(['default_location_id' => 'The default location must belong to the selected branch.']);
        }

        $attributes = Arr::only($data, [
            'branch_id', 'equipment_category_id', 'name', 'make', 'model', 'model_year',
            'serial_number', 'registration_number', 'chassis_number', 'ownership_type',
            'owner_customer_id', 'owner_name', 'capacity_value', 'capacity_unit', 'acquired_on',
            'acquisition_amount', 'acquisition_currency_code', 'hire_rate', 'hire_rate_basis',
            'default_location_id', 'meter_type', 'starting_meter_reading', 'starting_meter_date',
            'fuel_efficiency_basis', 'expected_fuel_efficiency', 'fuel_tolerance_percent',
            'tank_capacity', 'current_status', 'condition_summary', 'is_active',
        ]);

        if (! $actor->can('equipment.costs.view')) {
            unset(
                $attributes['acquisition_amount'],
                $attributes['acquisition_currency_code'],
                $attributes['hire_rate'],
                $attributes['hire_rate_basis'],
            );
        }

        if ($equipment instanceof Equipment) {
            unset($attributes['current_status'], $attributes['is_active']);
        }
        $attributes['tenant_id'] = $this->tenantContext->id();
        $attributes['asset_code'] = Str::upper((string) $data['asset_code']);
        $attributes['updated_by'] = $actor->id;

        if (! $equipment instanceof Equipment) {
            $attributes['current_location_id'] = $data['default_location_id'] ?? null;
            $attributes['current_meter_reading'] = $data['starting_meter_reading'] ?? null;
            $attributes['current_meter_read_at'] = $data['starting_meter_date'] ?? null;
        }

        $oldValues = $equipment?->only(array_keys($attributes)) ?? [];
        if ($equipment instanceof Equipment) {
            $equipment->update($attributes);
            $event = 'equipment.asset.updated';
        } else {
            $equipment = Equipment::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'equipment.asset.created';
        }
        $this->auditLogger->record($event, $equipment, $actor, $oldValues, $equipment->fresh()?->toArray() ?? []);

        return $equipment;
    }
}
