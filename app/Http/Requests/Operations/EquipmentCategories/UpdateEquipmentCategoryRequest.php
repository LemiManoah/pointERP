<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentCategories;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEquipmentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $category = $this->route('equipment_category');
        $id = $category instanceof EquipmentCategory ? $category->id : null;

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique((new EquipmentCategory)->getTable(), 'code')->where('tenant_id', $tenantId)->ignore($id)],
            'name' => ['required', 'string', 'max:120', Rule::unique((new EquipmentCategory)->getTable(), 'name')->where('tenant_id', $tenantId)->ignore($id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_meter_type' => ['required', 'string', Rule::in(Equipment::METER_TYPES)],
            'default_capacity_unit' => ['nullable', 'string', 'max:40'],
            'fuel_efficiency_basis' => ['nullable', 'string', Rule::in(['litres_per_hour', 'litres_per_100km'])],
            'expected_fuel_efficiency' => ['nullable', 'numeric', 'min:0'],
            'fuel_tolerance_percent' => ['nullable', 'numeric', 'between:0,100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper((string) $this->input('code'))]);
    }
}
