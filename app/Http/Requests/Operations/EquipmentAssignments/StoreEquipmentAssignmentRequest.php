<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentAssignments;

use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreEquipmentAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $equipment = $this->route('equipment');

        return $user instanceof User
            && $equipment instanceof Equipment
            && Gate::forUser($user)->allows('view', $equipment)
            && Gate::forUser($user)->allows('create', EquipmentAssignment::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid'],
            'site_id' => ['required', 'uuid'],
            'equipment_location_id' => ['required', 'uuid'],
            'custodian_staff_id' => ['nullable', 'uuid'],
            'external_custodian_name' => ['nullable', 'string', 'max:255'],
            'external_custodian_employer' => ['nullable', 'string', 'max:255'],
            'assigned_at' => ['required', 'date'],
            'expected_return_at' => ['nullable', 'date'],
            'handover_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'handover_condition' => ['required', 'string', 'max:4000'],
            'assignment_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
