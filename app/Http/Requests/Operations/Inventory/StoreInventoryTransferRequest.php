<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\UnitOfMeasure;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryTransferRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'source_store_id' => ['required', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'destination_store_id' => ['required', 'uuid', 'different:source_store_id', Rule::exists((new InventoryStore)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'transfer_key' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'uuid', Rule::exists((new InventoryItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.unit_of_measure_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->where('is_active', true)],
            'lines.*.inventory_batch_id' => ['nullable', 'uuid', Rule::exists((new InventoryBatch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
