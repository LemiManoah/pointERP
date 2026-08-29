<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\DailySiteReports;

use App\Models\Equipment;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\Rule;

final class StoreDailySiteReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'site_id' => ['required', 'uuid', Rule::exists((new Site)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'report_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'weather' => ['nullable', 'string', 'max:255'],
            'site_conditions' => ['nullable', 'string', 'max:255'],
            'work_summary' => ['nullable', 'string'],
            'delay_summary' => ['nullable', 'string'],
            'visitor_summary' => ['nullable', 'string'],
            'hse_notes' => ['nullable', 'string'],
            'environment_notes' => ['nullable', 'string'],
            'social_notes' => ['nullable', 'string'],
            'completion_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ...$this->lineRules($tenantId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineRules(string $tenantId): array
    {
        return [
            'work_lines' => ['array'],
            'work_lines.*.project_activity_id' => ['nullable', 'uuid', Rule::exists((new ProjectActivity)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'work_lines.*.site_id' => ['nullable', 'uuid'],
            'work_lines.*.boq_item_number' => ['nullable', 'string', 'max:255'],
            'work_lines.*.description' => ['required_with:work_lines', 'string', 'max:255'],
            'work_lines.*.chainage_from' => ['nullable', 'string', 'max:255'],
            'work_lines.*.chainage_to' => ['nullable', 'string', 'max:255'],
            'work_lines.*.side' => ['nullable', 'string', 'max:255'],
            'work_lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'work_lines.*.unit' => ['nullable', 'string', 'max:50'],
            'work_lines.*.rate_amount' => ['nullable', 'numeric', 'min:0'],
            'work_lines.*.amount' => ['nullable', 'numeric'],
            'work_lines.*.currency_code' => ['nullable', 'string', 'size:3'],
            'work_lines.*.notes' => ['nullable', 'string'],
            'labour_lines' => ['array'],
            'labour_lines.*.trade_or_role' => ['required_with:labour_lines', 'string', 'max:255'],
            'labour_lines.*.subcontractor_name' => ['nullable', 'string', 'max:255'],
            'labour_lines.*.headcount' => ['nullable', 'integer', 'min:0'],
            'labour_lines.*.hours' => ['nullable', 'numeric', 'min:0'],
            'labour_lines.*.rate_amount' => ['nullable', 'numeric', 'min:0'],
            'labour_lines.*.amount' => ['nullable', 'numeric'],
            'labour_lines.*.currency_code' => ['nullable', 'string', 'size:3'],
            'labour_lines.*.notes' => ['nullable', 'string'],
            'equipment_lines' => ['array'],
            'equipment_lines.*.equipment_id' => ['nullable', 'uuid', 'distinct', Rule::exists((new Equipment)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'equipment_lines.*.equipment_name' => ['required_with:equipment_lines', 'string', 'max:255'],
            'equipment_lines.*.equipment_identifier' => ['nullable', 'string', 'max:255'],
            'equipment_lines.*.status' => ['nullable', 'string', 'max:255'],
            'equipment_lines.*.working_hours' => ['nullable', 'numeric', 'min:0'],
            'equipment_lines.*.idle_hours' => ['nullable', 'numeric', 'min:0'],
            'equipment_lines.*.opening_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'equipment_lines.*.closing_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'equipment_lines.*.fuel_type' => ['nullable', 'string', 'max:255'],
            'equipment_lines.*.fuel_quantity' => ['nullable', 'numeric', 'min:0'],
            'equipment_lines.*.fuel_transaction_type' => ['nullable', Rule::in(['issue', 'refuel', 'consumption', 'return'])],
            'equipment_lines.*.rate_amount' => ['nullable', 'numeric', 'min:0'],
            'equipment_lines.*.amount' => ['nullable', 'numeric'],
            'equipment_lines.*.currency_code' => ['nullable', 'string', 'size:3'],
            'equipment_lines.*.notes' => ['nullable', 'string'],
            'equipment_lines.*.evidence_note' => ['nullable', 'string', 'max:2000'],
            'material_lines' => ['array'],
            'material_lines.*.material_name' => ['required_with:material_lines', 'string', 'max:255'],
            'material_lines.*.inventory_item_id' => ['nullable', 'uuid', Rule::exists('inventory_items', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'material_lines.*.inventory_store_id' => ['nullable', 'uuid', Rule::exists('inventory_stores', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'material_lines.*.unit_of_measure_id' => ['nullable', 'uuid', Rule::exists('unit_of_measures', 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->where('is_active', true)],
            'material_lines.*.material_type' => ['nullable', 'string', 'max:255'],
            'material_lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'material_lines.*.unit' => ['nullable', 'string', 'max:50'],
            'material_lines.*.rate_amount' => ['nullable', 'numeric', 'min:0'],
            'material_lines.*.amount' => ['nullable', 'numeric'],
            'material_lines.*.currency_code' => ['nullable', 'string', 'size:3'],
            'material_lines.*.delivery_reference' => ['nullable', 'string', 'max:255'],
            'material_lines.*.notes' => ['nullable', 'string'],
            'cost_lines' => ['array'],
            'cost_lines.*.category' => ['required_with:cost_lines', 'string', 'max:255'],
            'cost_lines.*.description' => ['required_with:cost_lines', 'string', 'max:255'],
            'cost_lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'cost_lines.*.unit' => ['nullable', 'string', 'max:50'],
            'cost_lines.*.rate_amount' => ['nullable', 'numeric', 'min:0'],
            'cost_lines.*.amount' => ['nullable', 'numeric'],
            'cost_lines.*.currency_code' => ['nullable', 'string', 'size:3'],
            'cost_lines.*.notes' => ['nullable', 'string'],
            'delay_lines' => ['array'],
            'delay_lines.*.delay_type' => ['nullable', 'string', 'max:255'],
            'delay_lines.*.description' => ['required_with:delay_lines', 'string', 'max:255'],
            'delay_lines.*.hours_lost' => ['nullable', 'numeric', 'min:0'],
            'delay_lines.*.action_taken' => ['nullable', 'string'],
        ];
    }
}
