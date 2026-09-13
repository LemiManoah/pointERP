<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Workforce;

use App\Enums\AttendanceShift;
use App\Enums\AttendanceStatus;
use App\Enums\DsrLabourSource;
use App\Models\Customer;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveSiteAttendanceRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds();
        $projectId = (string) $this->input('project_id');

        return [
            'project_id' => ['required', 'uuid', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)->where('status', 'active')->whereIn('branch_id', $branchIds)],
            'site_id' => ['required', 'uuid', Rule::exists('sites', 'id')->where('tenant_id', $tenantId)->where('project_id', $projectId)->where('status', 'active')->whereIn('branch_id', $branchIds)],
            'attendance_date' => ['required', 'date'],
            'shift' => ['required', Rule::enum(AttendanceShift::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.labour_source' => ['required', Rule::enum(DsrLabourSource::class)],
            'records.*.staff_id' => ['nullable', 'uuid', Rule::exists('staff', 'id')->where('tenant_id', $tenantId)],
            'records.*.subcontractor_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)->where('type', Customer::TYPE_SUBCONTRACTOR)->where('status', 'active')],
            'records.*.workforce_trade_id' => ['required', 'uuid', Rule::exists('workforce_trades', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'records.*.worker_name_snapshot' => ['nullable', 'string', 'max:255'],
            'records.*.headcount' => ['required', 'integer', 'min:1', 'max:10000'],
            'records.*.attendance_status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.regular_hours_per_person' => ['required', 'numeric', 'min:0', 'max:24'],
            'records.*.overtime_hours_per_person' => ['required', 'numeric', 'min:0', 'max:24'],
            'records.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
