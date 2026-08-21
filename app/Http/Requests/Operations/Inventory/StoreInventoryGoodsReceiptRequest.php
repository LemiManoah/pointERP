<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryGoodsReceiptRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds();
        $actor = $this->user();
        $canViewCosts = $actor instanceof User && $actor->can('inventory.receipts.view-costs');

        return [
            'branch_id' => ['nullable', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('id', $branchIds)->where('status', 'active')],
            'inventory_store_id' => ['required', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'supplier_id' => ['required', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('type', [Customer::TYPE_SUPPLIER, Customer::TYPE_SUBCONTRACTOR])->where('status', 'active')],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'amount_paid' => [Rule::requiredIf($canViewCosts), 'nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'uuid', Rule::exists((new InventoryItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_of_measure_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->where('is_active', true)],
            'lines.*.unit_cost' => [Rule::requiredIf($canViewCosts), 'nullable', 'numeric', 'min:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.manufactured_on' => ['nullable', 'date'],
            'lines.*.expires_on' => ['nullable', 'date', 'after_or_equal:lines.*.manufactured_on'],
        ];
    }
}
