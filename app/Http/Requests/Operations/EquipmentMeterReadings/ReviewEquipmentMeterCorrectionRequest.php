<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\EquipmentMeterReadings;

use Illuminate\Foundation\Http\FormRequest;

final class ReviewEquipmentMeterCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['decision_note' => ['nullable', 'string', 'max:2000']];
    }
}
