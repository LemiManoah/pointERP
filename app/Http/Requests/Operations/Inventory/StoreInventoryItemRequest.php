<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Enums\InventoryMaterialClass;
use App\Enums\InventoryTrackingType;
use App\Models\Customer;
use App\Models\InventoryCategory;
use App\Models\UnitOfMeasure;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreInventoryItemRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'inventory_category_id' => ['required', 'uuid', Rule::exists((new InventoryCategory)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'stock_unit_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->where('is_active', true)],
            'preferred_supplier_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('type', [Customer::TYPE_SUPPLIER, Customer::TYPE_SUBCONTRACTOR])->where('status', 'active')],
            'code' => ['required', 'string', 'max:60', Rule::unique('inventory_items', 'code')->where('tenant_id', $tenantId)->ignore($this->route('inventoryItem')?->id)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'material_class' => ['required', Rule::enum(InventoryMaterialClass::class)],
            'tracking_type' => ['required', Rule::enum(InventoryTrackingType::class)],
            'batch_number' => ['nullable', Rule::requiredIf($this->input('tracking_type') === InventoryTrackingType::Batch->value), 'string', 'max:100'],
            'is_expires' => ['required', 'boolean', Rule::requiredIf($this->input('tracking_type') === InventoryTrackingType::Batch->value), 'accepted_if:tracking_type,batch'],
            'is_for_sale' => ['required', 'boolean'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_quantity' => ['nullable', 'numeric', 'gt:0'],
            'default_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'default_selling_price' => ['nullable', Rule::prohibitedIf(! $this->boolean('is_for_sale')), 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $code = mb_trim((string) $this->input('code'));
        if ($code === '') {
            $code = Str::slug((string) $this->input('name'));
        }

        $this->merge(['code' => mb_strtoupper($code)]);
    }
}
