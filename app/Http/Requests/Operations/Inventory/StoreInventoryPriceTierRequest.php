<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\InventoryPriceTier;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryPriceTierRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $priceTier = $this->route('inventoryPriceTier');
        $priceTierId = $priceTier instanceof InventoryPriceTier
            ? $priceTier->id
            : null;

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('inventory_price_tiers', 'code')->where('tenant_id', $tenantId)->ignore($priceTierId)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper(mb_trim((string) $this->input('code')))]);
    }
}
