<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\InventoryBatch;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReturnMaterialRequisitionLineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'inventory_batch_id' => ['nullable', 'uuid', Rule::exists((new InventoryBatch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'reason' => ['required', 'string', 'max:1000'],
            'source_key' => ['required', 'uuid'],
        ];
    }
}
