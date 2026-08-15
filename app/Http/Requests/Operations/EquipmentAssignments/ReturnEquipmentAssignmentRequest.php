<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentAssignments;

use App\Models\EquipmentAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ReturnEquipmentAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $assignment = $this->route('equipmentAssignment');

        return $user instanceof User
            && $assignment instanceof EquipmentAssignment
            && Gate::forUser($user)->allows('update', $assignment);
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
