<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryCategoryRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('inventory_categories', 'code')->where('tenant_id', resolve(\App\Services\TenantContext::class)->id())->ignore($this->route('inventoryCategory')?->id)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper((string) $this->input('code'))]);
    }
}
