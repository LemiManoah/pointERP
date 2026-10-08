<?php

declare(strict_types=1);

namespace App\Actions\Operations\Boq;

use App\Models\DailySiteReport;
use App\Models\Equipment;
use App\Models\ProjectEstimateLine;
use Illuminate\Validation\ValidationException;

final readonly class ValidateDayworkUsage
{
    public function handle(DailySiteReport $report): void
    {
        $items = ProjectEstimateLine::query()->where('item_type', 'daywork')
            ->whereHas('estimate', fn ($query) => $query->where('project_id', $report->project_id)->where('is_baseline', true))->get();
        $groups = [
            'labour' => [$report->labourLines()->where('work_type', 'daywork')->get(), 'daywork_workforce_trade_id', 'workforce_trade_id'],
            'material' => [$report->materialLines()->where('work_type', 'daywork')->get(), 'daywork_inventory_item_id', 'inventory_item_id'],
            'equipment' => [$report->equipmentLines()->where('work_type', 'daywork')->with('equipment')->get(), 'daywork_equipment_category_id', null],
        ];
        foreach ($groups as $type => [$lines, $targetField, $sourceField]) {
            foreach ($lines as $line) {
                $equipment = $sourceField === null ? $line->getRelation('equipment') : null;
                $source = $sourceField === null ? ($equipment instanceof Equipment ? $equipment->equipment_category_id : null) : $line->getAttribute($sourceField);
                $matching = $items->where('daywork_resource_type', $type)->where($targetField, $source);
                if ($source === null || $matching->count() !== 1) {
                    throw ValidationException::withMessages([$type.'_lines' => 'Chargeable daywork requires a matching resource in the approved BOQ. Use ordinary work for normal resource usage.']);
                }
            }
        }
    }
}
