<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Equipment\Maintenance;

use App\Models\EquipmentMaintenanceSchedule;
use App\Models\Equipment;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMaintenanceScheduleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('equipment.maintenance.manage') === true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $equipment = $this->route('equipment');
        $schedule = $this->route('equipmentMaintenanceSchedule');
        abort_unless($equipment instanceof Equipment, 404);
        $nameRule = Rule::unique((new EquipmentMaintenanceSchedule)->getTable(), 'name')
            ->where('tenant_id', $tenantId)
            ->where('equipment_id', $equipment->id);
        if ($schedule instanceof EquipmentMaintenanceSchedule) {
            $nameRule->ignore($schedule->id);
        }

        return [
            'maintenance_type' => ['required', 'string', Rule::in(EquipmentMaintenanceSchedule::TYPES)],
            'name' => ['required', 'string', 'max:160', $nameRule],
            'basis' => ['required', 'string', Rule::in(EquipmentMaintenanceSchedule::BASES)],
            'interval_days' => ['nullable', 'required_if:basis,date,whichever_first', 'integer', 'min:1', 'max:3650'],
            'interval_meter_units' => ['nullable', 'required_if:basis,meter,whichever_first', 'numeric', 'gt:0'],
            'last_service_date' => ['nullable', 'date'],
            'last_service_reading' => ['nullable', 'numeric', 'min:0'],
            'warning_days' => ['required', 'integer', 'min:0', 'max:365'],
            'warning_meter_units' => ['required', 'numeric', 'min:0'],
            'responsible_user_id' => ['nullable', 'uuid', Rule::exists((new User)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
