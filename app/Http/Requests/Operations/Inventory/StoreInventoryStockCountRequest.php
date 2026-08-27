<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryStockCountRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'inventory_store_id' => ['required', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'count_key' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'uuid', Rule::exists((new InventoryItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.inventory_batch_id' => ['nullable', 'uuid', Rule::exists((new InventoryBatch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.system_quantity' => ['required', 'numeric', 'gte:0'],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
