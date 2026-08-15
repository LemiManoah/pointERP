<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\ReportingCalendars;

use App\Models\ReportingCalendarException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreReportingCalendarExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exception_date' => ['required', 'date'],
            'type' => ['required', Rule::in([
                ReportingCalendarException::TYPE_NON_WORKING,
                ReportingCalendarException::TYPE_WORKING_OVERRIDE,
            ])],
            'name' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

