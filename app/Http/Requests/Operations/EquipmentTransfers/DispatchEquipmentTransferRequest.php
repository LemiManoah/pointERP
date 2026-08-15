<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentTransfers;

use App\Models\EquipmentTransfer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class DispatchEquipmentTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $transfer = $this->route('equipmentTransfer');

        return $user instanceof User
            && $transfer instanceof EquipmentTransfer
            && Gate::forUser($user)->allows('dispatch', $transfer);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'dispatched_at' => ['required', 'date'],
            'dispatch_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'dispatch_condition' => ['required', 'string', 'max:4000'],
            'transport_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
