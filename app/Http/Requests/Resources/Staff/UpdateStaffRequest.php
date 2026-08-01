<?php

declare(strict_types=1);

namespace App\Http\Requests\Resources\Staff;

use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffPosition;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStaffRequest extends FormRequest
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
        /** @var Staff $staff */
        $staff = $this->route('staff');

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')],
            'staff_position_id' => ['required', 'uuid', Rule::exists((new StaffPosition)->getTable(), 'id')],
            'staff_number' => ['required', 'string', 'max:60', Rule::unique((new Staff)->getTable(), 'staff_number')->ignore($staff->id)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new ValidEmail, Rule::unique((new Staff)->getTable(), 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'staff_number' => mb_strtoupper((string) $this->input('staff_number')),
        ]);
    }
}
