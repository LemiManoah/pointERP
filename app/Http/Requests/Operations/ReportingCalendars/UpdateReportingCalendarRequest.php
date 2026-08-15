<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\ReportingCalendars;

use App\Models\Project;
use App\Models\Site;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateReportingCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'project_id' => ['nullable', 'uuid', Rule::exists((new Project)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'site_id' => ['nullable', 'uuid', Rule::exists((new Site)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'timezone' => ['required', 'timezone'],
            'reporting_deadline' => ['required', 'date_format:H:i'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'missing_escalation_days' => ['required', 'integer', 'between:1,30'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

