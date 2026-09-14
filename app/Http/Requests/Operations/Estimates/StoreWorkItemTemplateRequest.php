<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Estimates;

use App\Enums\EstimateResourceType;
use App\Models\Customer;
use App\Models\EquipmentCategory;
use App\Models\InventoryItem;
use App\Models\UnitOfMeasure;
use App\Models\WorkforceTrade;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @phpstan-type WorkItemResourceTemplatePayload array{resource_type: string, inventory_item_id?: string|null, unit_of_measure_id?: string|null, equipment_category_id?: string|null, workforce_trade_id?: string|null, subcontractor_id?: string|null, name: string, quantity_per_work_unit: numeric-string, unit_cost?: numeric-string|null, notes?: string|null}
 * @phpstan-type WorkItemTemplatePayload array{code?: string|null, category: string, name: string, unit_of_measure_id: string, default_selling_rate?: numeric-string|null, default_unit_cost?: numeric-string|null, specifications?: string|null, is_active?: bool, resources?: list<WorkItemResourceTemplatePayload>}
 */
final class StoreWorkItemTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $unitRule = Rule::exists((new UnitOfMeasure)->getTable(), 'id')
            ->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->where('is_active', true);

        return [
            'code' => ['nullable', 'string', 'max:80'],
            'category' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:220'],
            'unit_of_measure_id' => ['required', 'uuid', $unitRule],
            'default_selling_rate' => ['nullable', 'numeric', 'min:0'],
            'default_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'specifications' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'resources' => ['sometimes', 'array', 'max:100'],
            'resources.*.resource_type' => ['required', Rule::enum(EstimateResourceType::class)],
            'resources.*.inventory_item_id' => ['nullable', 'uuid', Rule::exists((new InventoryItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'resources.*.unit_of_measure_id' => ['nullable', 'uuid', $unitRule],
            'resources.*.equipment_category_id' => ['nullable', 'uuid', Rule::exists((new EquipmentCategory)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'resources.*.workforce_trade_id' => ['nullable', 'uuid', Rule::exists((new WorkforceTrade)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'resources.*.subcontractor_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('type', Customer::TYPE_SUBCONTRACTOR)->where('status', 'active')],
            'resources.*.name' => ['required', 'string', 'max:220'],
            'resources.*.quantity_per_work_unit' => ['required', 'numeric', 'gt:0'],
            'resources.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'resources.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
