<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BoqItemType;
use App\Models\BoqProgressEntry;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportMaterialLine;
use App\Models\ExpenseLine;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use Illuminate\Database\Eloquent\Builder;

final class ProjectPerformanceSummary
{
    /** @return array<string, mixed>|null */
    public function forProject(Project $project, bool $canViewCosts): ?array
    {
        $baseline = ProjectEstimate::query()
            ->with(['lines.unit', 'lines.resources.inventoryItem.stockUnit', 'lines.resources.unit'])
            ->where('project_id', $project->id)
            ->where('is_baseline', true)
            ->first();

        if (! $baseline instanceof ProjectEstimate) {
            return null;
        }

        $activities = ProjectActivity::query()
            ->where('project_id', $project->id)
            ->whereIn('estimate_work_item_key', $baseline->lines->pluck('work_item_key'))
            ->get()
            ->keyBy('estimate_work_item_key');

        $progress = BoqProgressEntry::query()->where('project_id', $project->id)
            ->selectRaw('boq_item_id, SUM(quantity) as approved_quantity')->groupBy('boq_item_id')
            ->pluck('approved_quantity', 'boq_item_id');
        $rows = $baseline->lines->map(function (ProjectEstimateLine $line) use ($activities, $progress, $canViewCosts): array {
            $activity = $activities->get($line->work_item_key);
            $approved = $line->boq_item_id ? (float) ($progress->get($line->boq_item_id) ?? 0) : ($activity instanceof ProjectActivity ? (float) $activity->approved_quantity : 0.0);
            $planned = (float) $line->planned_quantity;
            $remaining = max($planned - $approved, 0.0);
            $completion = $planned > 0 ? (($approved / $planned) * 100) : 0.0;
            $rate = $line->selling_rate === null ? null : (float) $line->selling_rate;
            $unitCost = $line->estimated_unit_cost === null ? null : (float) $line->estimated_unit_cost;

            return [
                'id' => $line->id,
                'boq_item_id' => $line->boq_item_id,
                'overrun_quantity' => number_format(max($approved - $planned, 0), 4, '.', ''),
                'work_item_id' => $activity?->id,
                'boq_reference' => $line->boq_reference,
                'bill' => $line->bill,
                'section' => $line->section,
                'element' => $line->element,
                'item_type' => $line->item_type->value,
                'name' => $line->name,
                'unit' => $line->unit->symbol ?? $line->unit->code,
                'planned_quantity' => $line->planned_quantity,
                'approved_progress' => number_format($approved, 4, '.', ''),
                'remaining_quantity' => number_format($remaining, 4, '.', ''),
                'completion_percent' => number_format($completion, 2, '.', ''),
                'baseline_revenue' => $canViewCosts && $rate !== null ? number_format($planned * $rate, 4, '.', '') : null,
                'earned_output' => $canViewCosts && $rate !== null && $line->item_type === BoqItemType::Measured ? number_format($approved * $rate, 4, '.', '') : null,
                'baseline_cost' => $canViewCosts && $unitCost !== null ? number_format($planned * $unitCost, 4, '.', '') : null,
            ];
        })->values();

        $approvedReports = DailySiteReport::query()
            ->where('project_id', $project->id)
            ->where('status', DailySiteReport::STATUS_APPROVED)
            ->get(['input_cost']);
        $approvedExpenseQuery = ExpenseLine::query()
            ->where('project_id', $project->id)
            ->whereHas('expense', fn (Builder $query): Builder => $query->where('status', 'approved'));
        $approvedExpenseCost = (float) (clone $approvedExpenseQuery)->sum('base_currency_amount');
        $materialActuals = DailySiteReportMaterialLine::query()
            ->whereNotNull('inventory_item_id')
            ->whereHas('report', fn (Builder $query): Builder => $query
                ->where('project_id', $project->id)
                ->where('status', DailySiteReport::STATUS_APPROVED))
            ->selectRaw('inventory_item_id, SUM(stock_unit_quantity) as actual_quantity')
            ->groupBy('inventory_item_id')
            ->pluck('actual_quantity', 'inventory_item_id');
        /** @var array<string, array{inventory_item_id: string, name: string, unit: string, planned_quantity: float, expected_to_date: float, actual_quantity: float}> $resourceRows */
        $resourceRows = [];

        foreach ($baseline->lines as $line) {
            $activity = $activities->get($line->work_item_key);
            $approved = $line->boq_item_id ? (float) ($progress->get($line->boq_item_id) ?? 0) : ($activity instanceof ProjectActivity ? (float) $activity->approved_quantity : 0.0);

            foreach ($line->resources as $resource) {
                if ($resource->resource_type->value !== 'material') {
                    continue;
                }

                if ($resource->inventory_item_id === null) {
                    continue;
                }

                $key = $resource->inventory_item_id;
                $quantityPerUnit = (float) $resource->quantity_per_work_unit;
                $current = $resourceRows[$key] ?? [
                    'inventory_item_id' => $key,
                    'name' => $resource->inventoryItem->name,
                    'unit' => $resource->unit->symbol ?? $resource->inventoryItem->stockUnit->symbol ?? '',
                    'planned_quantity' => 0.0,
                    'expected_to_date' => 0.0,
                    'actual_quantity' => (float) ($materialActuals->get($key) ?? 0),
                ];
                $current['planned_quantity'] += (float) $line->planned_quantity * $quantityPerUnit;
                $current['expected_to_date'] += $approved * $quantityPerUnit;
                $resourceRows[$key] = $current;
            }
        }

        $resources = collect($resourceRows)->map(function (array $row): array {
            $actual = (float) $row['actual_quantity'];
            $expected = (float) $row['expected_to_date'];

            return [
                ...$row,
                'planned_quantity' => number_format((float) $row['planned_quantity'], 4, '.', ''),
                'expected_to_date' => number_format($expected, 4, '.', ''),
                'actual_quantity' => number_format($actual, 4, '.', ''),
                'variance_quantity' => number_format($actual - $expected, 4, '.', ''),
            ];
        })->values()->all();

        return [
            'baseline' => [
                'id' => $baseline->id,
                'title' => $baseline->title,
                'version_number' => $baseline->version_number,
                'currency_code' => $baseline->currency_code,
                'approved_at' => $baseline->approved_at?->toDateTimeString(),
            ],
            'pricing' => $canViewCosts ? $baseline->pricingSummary() : null,
            'totals' => [
                'planned_items' => $rows->count(),
                'baseline_revenue' => $canViewCosts && $baseline->pricingSummary()['status'] === 'fully_priced' ? number_format($rows->sum(fn (array $row): float => (float) $row['baseline_revenue']), 4, '.', '') : null,
                'earned_output' => $canViewCosts && $rows->every(fn (array $row): bool => $row['earned_output'] !== null) ? number_format($rows->sum(fn (array $row): float => (float) $row['earned_output']), 4, '.', '') : null,
                'baseline_cost' => $canViewCosts && $rows->every(fn (array $row): bool => $row['baseline_cost'] !== null) ? number_format($rows->sum(fn (array $row): float => (float) $row['baseline_cost']), 4, '.', '') : null,
                'operational_expenses' => $canViewCosts ? number_format($approvedExpenseCost, 4, '.', '') : null,
                'actual_input_cost' => $canViewCosts ? number_format($approvedReports->sum(fn (DailySiteReport $report): float => (float) $report->input_cost) + $approvedExpenseCost, 4, '.', '') : null,
            ],
            'work_items' => $rows->all(),
            'resources' => $resources,
        ];
    }
}
