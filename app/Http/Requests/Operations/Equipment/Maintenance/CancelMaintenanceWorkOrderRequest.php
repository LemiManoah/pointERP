<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Equipment\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CancelMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:3000'],
            'release_status' => ['required', 'string', Rule::in(['available', 'assigned', 'idle', 'out_of_service'])],
        ];
    }
}
