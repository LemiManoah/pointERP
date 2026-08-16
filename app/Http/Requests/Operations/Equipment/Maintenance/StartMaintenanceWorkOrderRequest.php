<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Equipment\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

final class StartMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'actual_start_at' => ['required', 'date', 'before_or_equal:now'],
            'opening_meter_reading' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
