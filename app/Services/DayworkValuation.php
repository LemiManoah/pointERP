<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BoqItemType;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\DailySiteReportLabourLine;
use App\Models\DailySiteReportMaterialLine;
use App\Models\DsrEquipmentLineAdjustment;
use App\Models\Project;
use App\Models\ProjectEstimateLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class DayworkValuation
{
    /**
     * @param  Collection<int, ProjectEstimateLine>  $lines
     * @return array<string, array{quantity: string, value: string|null, evidence: list<array{id: string, report_id: string, report_reference: string, date: string, source: string, quantity: string}>}>
     */
    public function forProject(Project $project, Collection $lines): array
    {
        $dayworks = $lines->filter(fn (ProjectEstimateLine $line): bool => $line->item_type === BoqItemType::Daywork);
        /** @var array<string, array{quantity: float, rate: string|null, evidence: list<array{id: string, report_id: string, report_reference: string, date: string, source: string, quantity: string}>}> $result */
        $result = $dayworks->mapWithKeys(fn (ProjectEstimateLine $line): array => [$line->work_item_key => [
            'quantity' => 0.0, 'rate' => $line->selling_rate, 'evidence' => [],
        ]])->all();
        if ($result === []) {
            return [];
        }

        $approvedReport = fn (Builder $query): Builder => $query
            ->where('project_id', $project->id)
            ->whereIn('status', [DailySiteReport::STATUS_APPROVED, DailySiteReport::STATUS_ARCHIVED]);

        $labourTargets = $dayworks->where('daywork_resource_type', 'labour')->keyBy('daywork_workforce_trade_id');
        DailySiteReportLabourLine::query()->where('work_type', 'daywork')->with('report:id,reference,report_date')
            ->whereIn('workforce_trade_id', $labourTargets->keys())->whereHas('report', $approvedReport)
            ->get()->each(function (DailySiteReportLabourLine $usage) use (&$result, $labourTargets): void {
                $target = $labourTargets->get($usage->workforce_trade_id);
                if ($target instanceof ProjectEstimateLine) {
                    $result = $this->add($result, $target, $usage->id, $usage->report, $usage->trade_or_role, (float) $usage->headcount * (float) ($usage->hours ?? 0));
                }
            });

        $equipmentCorrections = DsrEquipmentLineAdjustment::query()
            ->whereHas('correction', fn (Builder $query): Builder => $query->where('status', 'approved')->whereHas('report', $approvedReport))
            ->get()->groupBy('daily_site_report_equipment_line_id');
        $equipmentTargets = $dayworks->where('daywork_resource_type', 'equipment')->keyBy('daywork_equipment_category_id');
        DailySiteReportEquipmentLine::query()->where('work_type', 'daywork')->with(['report:id,reference,report_date', 'equipment:id,equipment_category_id'])
            ->whereHas('equipment', fn (Builder $query): Builder => $query->whereIn('equipment_category_id', $equipmentTargets->keys()))
            ->whereHas('report', $approvedReport)->get()
            ->each(function (DailySiteReportEquipmentLine $usage) use (&$result, $equipmentTargets, $equipmentCorrections): void {
                $target = $equipmentTargets->get($usage->equipment?->equipment_category_id);
                if ($target instanceof ProjectEstimateLine) {
                    $result = $this->add($result, $target, $usage->id, $usage->report, $usage->equipment_name, (float) ($usage->working_hours ?? 0) + (float) $equipmentCorrections->get($usage->id, collect())->sum('working_hours_delta'));
                }
            });

        $materialTargets = $dayworks->where('daywork_resource_type', 'material')->keyBy('daywork_inventory_item_id');
        DailySiteReportMaterialLine::query()->where('work_type', 'daywork')->with('report:id,reference,report_date')
            ->whereIn('inventory_item_id', $materialTargets->keys())->whereHas('report', $approvedReport)
            ->get()->each(function (DailySiteReportMaterialLine $usage) use (&$result, $materialTargets): void {
                $target = $materialTargets->get($usage->inventory_item_id);
                if ($target instanceof ProjectEstimateLine) {
                    $result = $this->add($result, $target, $usage->id, $usage->report, $usage->material_name, (float) ($usage->stock_unit_quantity ?? 0));
                }
            });

        return collect($result)->map(function (array $row): array {
            $quantity = (float) $row['quantity'];
            $rate = $row['rate'];

            return [
                'quantity' => number_format($quantity, 4, '.', ''),
                'value' => $rate === null ? null : number_format($quantity * (float) $rate, 4, '.', ''),
                'evidence' => collect($row['evidence'])->sortBy(fn (array $evidence): string => $evidence['date'].'|'.$evidence['report_reference'].'|'.$evidence['id'])->values()->all(),
            ];
        })->all();
    }

    /**
     * @param  array<string, array{quantity: float, rate: string|null, evidence: list<array{id: string, report_id: string, report_reference: string, date: string, source: string, quantity: string}>}>  $result
     * @return array<string, array{quantity: float, rate: string|null, evidence: list<array{id: string, report_id: string, report_reference: string, date: string, source: string, quantity: string}>}>
     */
    private function add(array $result, ProjectEstimateLine $target, string $id, DailySiteReport $report, string $source, float $quantity): array
    {
        if ($quantity <= 0) {
            return $result;
        }

        $result[$target->work_item_key]['quantity'] += $quantity;
        $result[$target->work_item_key]['evidence'][] = [
            'id' => $id, 'report_id' => $report->id, 'report_reference' => $report->reference,
            'date' => $report->report_date->toDateString(), 'source' => $source,
            'quantity' => number_format($quantity, 4, '.', ''),
        ];

        return $result;
    }
}
