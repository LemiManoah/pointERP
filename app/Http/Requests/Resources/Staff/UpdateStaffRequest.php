<?php

declare(strict_types=1);

namespace App\Http\Requests\Resources\Staff;

use App\Enums\StaffEmploymentType;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffPosition;
use App\Models\WorkforceTrade;
use App\Rules\ValidEmail;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $staff = $this->route('staff');
        abort_unless($staff instanceof Staff, 404);
        $tenantId = resolve(TenantContext::class)->id();
        $accessibleBranchIds = resolve(BranchContext::class)->accessibleBranchIds();

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active'), Rule::in($accessibleBranchIds)],
            'staff_position_id' => ['required', 'uuid', Rule::exists((new StaffPosition)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'employment_type' => ['required', Rule::enum(StaffEmploymentType::class)],
            'primary_trade_id' => ['nullable', 'uuid', Rule::exists((new WorkforceTrade)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'staff_number' => ['required', 'string', 'max:60', Rule::unique((new Staff)->getTable(), 'staff_number')->where('tenant_id', $tenantId)->ignore($staff->id)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new ValidEmail, Rule::unique((new Staff)->getTable(), 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['staff_number' => mb_strtoupper((string) $this->input('staff_number'))]);
    }
}
