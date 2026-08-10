<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Contracts;

use App\Models\Branch;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Customer;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds();
        $contract = $this->route('contract');

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active'), Rule::in($branchIds)],
            'customer_id' => ['required', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'reference' => ['required', 'string', 'max:80', Rule::unique((new Contract)->getTable(), 'reference')->where('tenant_id', $tenantId)->ignore($contract instanceof Contract ? $contract->id : null)],
            'title' => ['required', 'string', 'max:180'],
            'scope_summary' => ['nullable', 'string', 'max:2000'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists((new Currency)->getTable(), 'code')->where('is_active', true)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'completed', 'closed', 'archived'])],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'reference' => mb_strtoupper((string) $this->input('reference')),
            'currency_code' => mb_strtoupper((string) $this->input('currency_code')),
        ]);
    }
}
