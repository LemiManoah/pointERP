<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\Branch;
use App\Models\UnitOfMeasure;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryItemPriceRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'tier_code' => ['required', 'string', 'max:40'],
            'tier_name' => ['required', 'string', 'max:100'],
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('id', resolve(BranchContext::class)->accessibleBranchIds())->where('status', 'active')],
            'unit_of_measure_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:0'],
            'minimum_quantity' => ['nullable', 'numeric', 'gt:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['tier_code' => mb_strtoupper((string) $this->input('tier_code'))]);
    }
}
