<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentLocations;

use App\Models\Branch;
use App\Models\EquipmentLocation;
use App\Models\Project;
use App\Models\Site;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEquipmentLocationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active'), Rule::in(resolve(BranchContext::class)->accessibleBranchIds())],
            'project_id' => ['nullable', 'uuid', Rule::exists((new Project)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'site_id' => ['nullable', 'uuid', Rule::exists((new Site)->getTable(), 'id')->where('tenant_id', $tenantId), Rule::unique((new EquipmentLocation)->getTable(), 'site_id')->where('tenant_id', $tenantId)],
            'type' => ['required', 'string', Rule::in(EquipmentLocation::TYPES)],
            'code' => ['required', 'string', 'max:40', Rule::unique((new EquipmentLocation)->getTable(), 'code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->input('project_id') === '' ? null : $this->input('project_id'),
            'site_id' => $this->input('site_id') === '' ? null : $this->input('site_id'),
            'code' => mb_strtoupper((string) $this->input('code')),
        ]);
    }
}
