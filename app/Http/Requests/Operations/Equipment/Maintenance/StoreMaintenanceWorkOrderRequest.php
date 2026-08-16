<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Equipment\Maintenance;

use App\Models\Customer;
use App\Models\EquipmentMaintenanceSchedule;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->can('equipment.maintenance.request') === true) {
            return true;
        }

        return $this->user()?->can('equipment.maintenance.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'equipment_maintenance_schedule_id' => ['nullable', 'uuid', Rule::exists((new EquipmentMaintenanceSchedule)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'reference' => ['required', 'string', 'max:100', Rule::unique((new EquipmentMaintenanceWorkOrder)->getTable(), 'reference')->where('tenant_id', $tenantId)],
            'maintenance_type' => ['required', 'string', Rule::in(EquipmentMaintenanceSchedule::TYPES)],
            'priority' => ['required', 'string', Rule::in(EquipmentMaintenanceWorkOrder::PRIORITIES)],
            'description' => ['required', 'string', 'max:5000'],
            'reported_at' => ['required', 'date', 'before_or_equal:now'],
            'planned_start_at' => ['nullable', 'date', 'after_or_equal:reported_at'],
            'provider_customer_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')->whereIn('type', [Customer::TYPE_SUPPLIER, Customer::TYPE_SUBCONTRACTOR])],
            'provider_name' => ['nullable', 'string', 'max:200'],
        ];
    }
}
