<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\ProjectActivities;

use App\Models\Currency;
use App\Models\Project;
use App\Models\Site;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProjectActivityRequest extends FormRequest
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
            'project_id' => ['required', 'uuid', Rule::exists((new Project)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'site_id' => ['nullable', 'uuid', Rule::exists((new Site)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'code' => ['nullable', 'string', 'max:80'],
            'boq_item_number' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:220'],
            'unit' => ['nullable', 'string', 'max:40'],
            'planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'approved_quantity' => ['nullable', 'numeric', 'min:0'],
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3', Rule::exists((new Currency)->getTable(), 'code')->where('is_active', true)],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'site_id' => $this->input('site_id') === '' ? null : $this->input('site_id'),
            'currency_code' => $this->input('currency_code') === '' ? null : mb_strtoupper((string) $this->input('currency_code')),
            'code' => $this->input('code') === '' ? null : $this->input('code'),
            'boq_item_number' => $this->input('boq_item_number') === '' ? null : $this->input('boq_item_number'),
        ]);
    }
}
