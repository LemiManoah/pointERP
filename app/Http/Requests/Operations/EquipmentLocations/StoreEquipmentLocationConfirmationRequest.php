<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentLocations;

use App\Models\Equipment;
use App\Models\EquipmentLocationConfirmation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreEquipmentLocationConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $equipment = $this->route('equipment');

        return $user instanceof User
            && $equipment instanceof Equipment
            && Gate::forUser($user)->allows('view', $equipment)
            && Gate::forUser($user)->allows('create', EquipmentLocationConfirmation::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'equipment_location_id' => ['required', 'uuid'],
            'observed_at' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'observed_status' => ['nullable', 'in:available,assigned,idle,under_maintenance,out_of_service'],
            'condition_observation' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
