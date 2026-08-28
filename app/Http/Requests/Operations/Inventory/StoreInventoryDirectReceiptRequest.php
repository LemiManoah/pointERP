<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Enums\InventoryDirectReceiptReason;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\UnitOfMeasure;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryDirectReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'receipt_key' => ['required', 'uuid'],
            'return_to' => ['required', 'string', 'max:500', 'regex:/^\/(?!\/)[A-Za-z0-9\/_-]*$/'],
            'inventory_store_id' => ['required', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'source_company_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'source_reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', Rule::enum(InventoryDirectReceiptReason::class)],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_item_id' => ['required', 'uuid', Rule::exists((new InventoryItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.unit_of_measure_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.manufactured_on' => ['nullable', 'date', 'before_or_equal:received_on'],
            'lines.*.expires_on' => ['nullable', 'date', 'after:received_on'],
        ];
    }
}
