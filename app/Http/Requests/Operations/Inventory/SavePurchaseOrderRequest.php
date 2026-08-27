<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\TenantCurrency;
use App\Models\UnitOfMeasure;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds();

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('id', $branchIds)->where('status', 'active')],
            'inventory_store_id' => ['required', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'supplier_id' => ['required', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('type', Customer::TYPE_SUPPLIER)->where('status', 'active')],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists((new TenantCurrency)->getTable(), 'currency_code')->where('tenant_id', $tenantId)->where('is_enabled', true)],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_terms' => ['nullable', 'string', 'max:3000'],
            'payment_terms' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_item_id' => ['required', 'uuid', 'distinct', Rule::exists((new InventoryItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.unit_of_measure_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))],
            'lines.*.ordered_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
