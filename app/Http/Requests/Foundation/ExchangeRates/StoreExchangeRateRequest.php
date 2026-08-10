<?php

declare(strict_types=1);

namespace App\Http\Requests\Foundation\ExchangeRates;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\TenantCurrency;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $accessibleBranchIds = resolve(BranchContext::class)->accessibleBranchIds();

        return [
            'branch_id' => ['nullable', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active'), Rule::in($accessibleBranchIds)],
            'from_currency_code' => ['required', 'string', 'size:3', 'different:to_currency_code', Rule::exists((new Currency)->getTable(), 'code')->where('is_active', true), Rule::exists((new TenantCurrency)->getTable(), 'currency_code')->where('tenant_id', $tenantId)->where('is_enabled', true)],
            'to_currency_code' => ['required', 'string', 'size:3', Rule::exists((new Currency)->getTable(), 'code')->where('is_active', true), Rule::exists((new TenantCurrency)->getTable(), 'currency_code')->where('tenant_id', $tenantId)->where('is_enabled', true)],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_date' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('branch_id') === null && ! resolve(BranchContext::class)->canViewAllBranches()) {
                $validator->errors()->add('branch_id', 'Only all-branch users can create a tenant-wide exchange rate.');
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->input('branch_id') === '__tenant__' ? null : $this->input('branch_id'),
            'from_currency_code' => mb_strtoupper((string) $this->input('from_currency_code')),
            'to_currency_code' => mb_strtoupper((string) $this->input('to_currency_code')),
            'expires_at' => $this->input('expires_at') ?: null,
        ]);
    }
}
