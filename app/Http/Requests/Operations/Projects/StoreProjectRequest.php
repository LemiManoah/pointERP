<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Projects;

use App\Models\Branch;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectRequest extends FormRequest
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
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds();

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active'), Rule::in($branchIds)],
            'customer_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'contract_id' => ['nullable', 'uuid', Rule::exists((new Contract)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'reference' => ['required', 'string', 'max:80', Rule::unique((new Project)->getTable(), 'reference')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'uuid', Rule::exists((new User)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'base_currency_code' => ['required', 'string', 'size:3', Rule::exists((new Currency)->getTable(), 'code')->where('is_active', true)],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reporting_deadline' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'string', Rule::in(['planned', 'active', 'on_hold', 'completed', 'closed', 'archived'])],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'customer_id' => $this->input('customer_id') === '' ? null : $this->input('customer_id'),
            'contract_id' => $this->input('contract_id') === '' ? null : $this->input('contract_id'),
            'manager_id' => $this->input('manager_id') === '' ? null : $this->input('manager_id'),
            'reference' => mb_strtoupper((string) $this->input('reference')),
            'base_currency_code' => mb_strtoupper((string) $this->input('base_currency_code')),
        ]);
    }
}
