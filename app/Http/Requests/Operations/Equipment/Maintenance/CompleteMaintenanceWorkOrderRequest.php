<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Equipment\Maintenance;

use App\Models\Customer;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'completed_at' => ['required', 'date', 'before_or_equal:now'],
            'closing_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'findings' => ['nullable', 'string', 'max:5000'],
            'work_performed' => ['required', 'string', 'max:10000'],
            'completion_notes' => ['nullable', 'string', 'max:5000'],
            'labour_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'parts' => ['array', 'max:100'],
            'parts.*.part_code' => ['nullable', 'string', 'max:100'],
            'parts.*.part_name' => ['required', 'string', 'max:200'],
            'parts.*.quantity' => ['required', 'numeric', 'gt:0'],
            'parts.*.unit' => ['required', 'string', 'max:30'],
            'parts.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'parts.*.provider_customer_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'parts.*.provider_name' => ['nullable', 'string', 'max:200'],
            'parts.*.reference' => ['nullable', 'string', 'max:160'],
            'parts.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
