<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\DailySiteReports;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveDailySiteReportRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'labour_variance_override_reason' => ['nullable', 'string', 'min:10', 'max:2000'],
        ];
    }
}
