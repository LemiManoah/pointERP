<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Enums\InventoryBatchStatus;
use App\Models\InventoryStore;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryBatchRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds();

        return [
            'inventory_store_id' => ['nullable', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds)->where('is_active', true)],
            'batch_number' => ['required', 'string', 'max:100'],
            'manufactured_on' => ['nullable', 'date'],
            'expires_on' => ['required', 'date', 'after_or_equal:manufactured_on'],
            'status' => ['required', Rule::enum(InventoryBatchStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
