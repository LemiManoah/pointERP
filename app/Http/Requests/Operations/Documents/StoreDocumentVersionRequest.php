<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDocumentVersionRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,webp,txt'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
