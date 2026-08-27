<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewMaterialRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'return', 'reject'])],
            'reason' => ['nullable', 'required_unless:decision,approve', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'],
            'lines.*.id' => ['required_with:lines', 'uuid'],
            'lines.*.approved_quantity' => ['required_with:lines', 'numeric', 'gte:0'],
        ];
    }
}
