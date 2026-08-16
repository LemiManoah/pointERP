<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Equipment\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['approval_note' => ['nullable', 'string', 'max:2000']];
    }
}
