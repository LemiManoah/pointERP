<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Equipment;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\TenantCurrency;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return $this->equipmentRules($tenantId, null);
    }

    public function prepareForValidation(): void
    {
        $this->normaliseInputs();
    }

    /** @return array<string, list<mixed>> */
    private function equipmentRules(string $tenantId, ?string $ignoreId): array
    {
        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active'), Rule::in(resolve(BranchContext::class)->accessibleBranchIds())],
            'equipment_category_id' => ['required', 'uuid', Rule::exists((new EquipmentCategory)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'asset_code' => ['required', 'string', 'max:60', Rule::unique((new Equipment)->getTable(), 'asset_code')->where('tenant_id', $tenantId)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:160'],
            'make' => ['nullable', 'string', 'max:120'], 'model' => ['nullable', 'string', 'max:120'],
            'model_year' => ['nullable', 'integer', 'between:1900,2100'],
            'serial_number' => ['nullable', 'string', 'max:160', Rule::unique((new Equipment)->getTable(), 'serial_number')->where('tenant_id', $tenantId)->ignore($ignoreId)],
            'registration_number' => ['nullable', 'string', 'max:80', Rule::unique((new Equipment)->getTable(), 'registration_number')->where('tenant_id', $tenantId)->ignore($ignoreId)],
            'chassis_number' => ['nullable', 'string', 'max:160'],
            'ownership_type' => ['required', 'string', Rule::in(Equipment::OWNERSHIP_TYPES)],
            'owner_customer_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('type', ['supplier', 'subcontractor'])->where('status', 'active')],
            'owner_name' => ['nullable', 'string', 'max:160'],
            'capacity_value' => ['nullable', 'numeric', 'min:0'], 'capacity_unit' => ['nullable', 'string', 'max:40'],
            'acquired_on' => ['nullable', 'date'], 'acquisition_amount' => ['nullable', 'numeric', 'min:0'],
            'acquisition_currency_code' => ['nullable', 'string', 'size:3', Rule::exists((new TenantCurrency)->getTable(), 'currency_code')->where('tenant_id', $tenantId)->where('is_enabled', true)],
            'hire_rate' => ['nullable', 'numeric', 'min:0'], 'hire_rate_basis' => ['nullable', 'string', Rule::in(['hour', 'day', 'week', 'month'])],
            'default_location_id' => ['nullable', 'uuid', Rule::exists((new EquipmentLocation)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'meter_type' => ['required', 'string', Rule::in(Equipment::METER_TYPES)],
            'starting_meter_reading' => ['nullable', 'numeric', 'min:0', 'required_unless:meter_type,none'],
            'starting_meter_date' => ['nullable', 'date', 'required_unless:meter_type,none'],
            'fuel_efficiency_basis' => ['nullable', 'string', Rule::in(['litres_per_hour', 'litres_per_100km'])],
            'expected_fuel_efficiency' => ['nullable', 'numeric', 'min:0'],
            'fuel_tolerance_percent' => ['nullable', 'numeric', 'between:0,100'],
            'tank_capacity' => ['nullable', 'numeric', 'min:0'],
            'current_status' => ['required', 'string', Rule::in(['available', 'idle', 'out_of_service'])],
            'condition_summary' => ['nullable', 'string', 'max:2000'], 'is_active' => ['required', 'boolean'],
        ];
    }

    private function normaliseInputs(): void
    {
        $nullable = ['owner_customer_id', 'default_location_id', 'acquisition_currency_code'];
        $values = ['asset_code' => mb_strtoupper((string) $this->input('asset_code'))];
        foreach ($nullable as $key) { $values[$key] = $this->input($key) === '' ? null : $this->input($key); }
        $this->merge($values);
    }
}
