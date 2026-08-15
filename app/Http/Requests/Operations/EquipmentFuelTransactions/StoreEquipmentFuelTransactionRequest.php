<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentFuelTransactions;

use App\Models\Equipment;
use App\Models\EquipmentFuelTransaction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreEquipmentFuelTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $equipment = $this->route('equipment');

        return $user instanceof User
            && $equipment instanceof Equipment
            && Gate::forUser($user)->allows('view', $equipment)
            && Gate::forUser($user)->allows('create', EquipmentFuelTransaction::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'transacted_at' => ['required', 'date'],
            'transaction_type' => ['required', 'in:issue,refuel,consumption,return,adjustment'],
            'fuel_type' => ['required', 'string', 'max:60'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'source_type' => ['required', 'in:supplier,store,site_stock,mobile_bowser,other'],
            'provider_customer_id' => ['nullable', 'uuid'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'tank_level_before' => ['nullable', 'numeric', 'min:0'],
            'tank_level_after' => ['nullable', 'numeric', 'min:0'],
            'is_full_tank' => ['required', 'boolean'],
            'received_by_staff_id' => ['nullable', 'uuid'],
            'voucher_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
