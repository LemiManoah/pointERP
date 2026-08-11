<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\DailySiteReports;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDailySiteReportCorrectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'changes' => ['required', 'array', 'min:1'],
            'changes.weather' => ['nullable', 'string', 'max:255'],
            'changes.site_conditions' => ['nullable', 'string', 'max:255'],
            'changes.work_summary' => ['nullable', 'string'],
            'changes.delay_summary' => ['nullable', 'string'],
            'changes.visitor_summary' => ['nullable', 'string'],
            'changes.hse_notes' => ['nullable', 'string'],
            'changes.environment_notes' => ['nullable', 'string'],
            'changes.social_notes' => ['nullable', 'string'],
            'changes.completion_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
