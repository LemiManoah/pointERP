<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Estimates;

use Illuminate\Foundation\Http\FormRequest;

final class ImportWorkItemTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']];
    }
}
