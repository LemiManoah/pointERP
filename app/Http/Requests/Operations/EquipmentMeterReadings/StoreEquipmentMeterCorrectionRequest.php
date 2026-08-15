<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentMeterReadings;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEquipmentMeterCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reading_value' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
