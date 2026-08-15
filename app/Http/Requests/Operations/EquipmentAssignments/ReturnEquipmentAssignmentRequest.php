<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentAssignments;

use Illuminate\Foundation\Http\FormRequest;

final class ReturnEquipmentAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'return_location_id' => ['required', 'uuid'],
            'returned_at' => ['required', 'date'],
            'return_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'return_condition' => ['required', 'string', 'max:4000'],
            'return_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
