<?php

declare(strict_types=1);

namespace App\Http\Requests\Foundation\CurrencySettings;

use App\Models\Branch;
use App\Models\Currency;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveBranchCurrencyRequest extends FormRequest
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

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists((new Currency)->getTable(), 'code')->where('is_active', true)],
            'is_enabled' => ['required', 'boolean'],
            'is_default_transaction_currency' => ['required', 'boolean'],
            'can_receive' => ['required', 'boolean'],
            'can_pay' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => mb_strtoupper((string) $this->input('currency_code')),
            'is_enabled' => $this->boolean('is_enabled'),
            'is_default_transaction_currency' => $this->boolean('is_default_transaction_currency'),
            'can_receive' => $this->boolean('can_receive'),
            'can_pay' => $this->boolean('can_pay'),
        ]);
    }
}
