<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\ExpectedDailySiteReports;

use Illuminate\Foundation\Http\FormRequest;

final class ExcuseExpectedDailySiteReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
