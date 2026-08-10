<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\DocumentTypes;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'requires_expiry_date' => ['boolean'],
            'is_confidential' => ['boolean'],
            'is_active' => ['boolean'],
            'tenant_specific' => ['boolean'],
        ];
    }
}
