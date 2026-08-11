<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\DailySiteReports;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitDailySiteReportRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'evidence_override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
