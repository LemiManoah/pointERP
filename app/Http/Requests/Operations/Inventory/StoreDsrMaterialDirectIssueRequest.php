<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\InventoryBatch;
use App\Models\InventoryStore;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDsrMaterialDirectIssueRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'inventory_store_id' => ['required', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'inventory_batch_id' => ['nullable', 'uuid', Rule::exists((new InventoryBatch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
