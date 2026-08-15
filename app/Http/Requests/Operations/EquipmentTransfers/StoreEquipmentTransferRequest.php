<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentTransfers;

use App\Models\Equipment;
use App\Models\EquipmentTransfer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreEquipmentTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $equipment = $this->route('equipment');

        return $user instanceof User
            && $equipment instanceof Equipment
            && Gate::forUser($user)->allows('view', $equipment)
            && Gate::forUser($user)->allows('create', EquipmentTransfer::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'destination_branch_id' => ['required', 'uuid'],
            'destination_location_id' => ['required', 'uuid'],
            'destination_project_id' => ['nullable', 'uuid'],
            'destination_site_id' => ['nullable', 'uuid'],
            'reason' => ['required', 'string', 'max:4000'],
        ];
    }
}
