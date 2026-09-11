<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\InventoryBatch;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IssueMaterialRequisitionLineRequest extends FormRequest
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
            'quantity' => ['required', 'numeric', 'gt:0'],
            'inventory_batch_id' => ['nullable', 'uuid', Rule::exists((new InventoryBatch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'reason' => ['required', 'string', 'max:1000'],
            'received_by_name' => ['required', 'string', 'max:180'],
            'received_on' => ['required', 'date'],
            'received_time' => ['required', 'date_format:H:i'],
            'handover_note' => ['nullable', 'string', 'max:2000'],
            'handover_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'source_key' => ['required', 'uuid'],
        ];
    }
}
