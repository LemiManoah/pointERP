<?php

declare(strict_types=1);

namespace App\Http\Requests\Resources\StaffPositions;

use App\Models\StaffPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStaffPositionRequest extends FormRequest
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
        /** @var StaffPosition $staffPosition */
        $staffPosition = $this->route('staff_position');

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', Rule::unique((new StaffPosition)->getTable(), 'code')->ignore($staffPosition->id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper((string) $this->input('code')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
