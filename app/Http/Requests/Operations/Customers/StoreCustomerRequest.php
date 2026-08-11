<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Customers;

use App\Models\Branch;
use App\Models\Customer;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomerRequest extends FormRequest
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
            'branch_id' => ['nullable', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active'), Rule::in($branchIds)],
            'type' => ['required', 'string', Rule::in([Customer::TYPE_CLIENT, Customer::TYPE_SUBCONTRACTOR, Customer::TYPE_SUPPLIER, Customer::TYPE_OTHER])],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:60', Rule::unique((new Customer)->getTable(), 'code')->where('tenant_id', $tenantId)],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->input('branch_id') === '' ? null : $this->input('branch_id'),
            'code' => mb_strtoupper((string) $this->input('code')),
        ]);
    }
}
