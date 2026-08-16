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
            'changes.equipment_adjustments' => ['nullable', 'array', 'max:20'],
            'changes.equipment_adjustments.*.line_id' => ['required', 'uuid', 'distinct'],
            'changes.equipment_adjustments.*.working_hours_delta' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'changes.equipment_adjustments.*.idle_hours_delta' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'changes.equipment_adjustments.*.fuel_quantity_delta' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'changes.equipment_adjustments.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
