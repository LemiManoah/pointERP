<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentFuelTransactions;

use App\Models\EquipmentFuelTransaction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ApproveEquipmentFuelTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $transaction = $this->route('equipmentFuelTransaction');

        return $user instanceof User
            && $transaction instanceof EquipmentFuelTransaction
            && Gate::forUser($user)->allows('approve', $transaction);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['approval_note' => ['nullable', 'string', 'max:2000']];
    }
}
