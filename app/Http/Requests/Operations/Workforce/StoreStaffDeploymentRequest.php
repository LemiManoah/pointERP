<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Workforce;

use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStaffDeploymentRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds();
        $projectId = (string) $this->input('project_id');

        return [
            'staff_id' => ['required', 'uuid', Rule::exists('staff', 'id')->where('tenant_id', $tenantId)->where('status', 'active')->whereIn('branch_id', $branchIds)],
            'project_id' => ['required', 'uuid', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)->where('status', 'active')->whereIn('branch_id', $branchIds)],
            'site_id' => ['nullable', 'uuid', Rule::exists('sites', 'id')->where('tenant_id', $tenantId)->where('project_id', $projectId)->where('status', 'active')->whereIn('branch_id', $branchIds)],
            'workforce_trade_id' => ['nullable', 'uuid', Rule::exists('workforce_trades', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'starts_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
