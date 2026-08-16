<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailySiteReport;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\DsrEquipmentLineAdjustment;
use App\Models\Equipment;
use App\Models\EquipmentFuelTransaction;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\EquipmentUsageLog;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class EquipmentScopeSummary
{
    /** @return array<string, mixed> */
    public function forProject(Project $project, User $user): array
    {
        return $this->build($project->tenant_id, $project->id, null, $user);
    }

    /** @return array<string, mixed> */
    public function forSite(Site $site, User $user): array
    {
        return $this->build($site->tenant_id, $site->project_id, $site->id, $user);
    }

    /** @return array<string, mixed> */
    private function build(string $tenantId, string $projectId, ?string $siteId, User $user): array
    {
        $equipment = Equipment::query()
            ->with(['category', 'currentLocation', 'currentCustodian'])
            ->where('tenant_id', $tenantId)
            ->where('current_project_id', $projectId)
            ->where('is_active', true)
            ->where('current_status', '!=', 'retired')
            ->when($siteId, fn (Builder $query, string $id): Builder => $query->where('current_site_id', $id))
            ->orderBy('asset_code')
            ->get()
            ->filter(fn (Equipment $asset): bool => Gate::forUser($user)->allows('view', $asset))
            ->values();

        $usageLogs = EquipmentUsageLog::query()
            ->with('equipment')
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->when($siteId, fn (Builder $query, string $id): Builder => $query->where('site_id', $id))
            ->where('usage_date', '>=', now()->subDays(30)->toDateString())
            ->where('status', 'posted')
            ->get()
            ->filter(fn (EquipmentUsageLog $log): bool => Gate::forUser($user)->allows('view', $log->equipment));

        $fuelTransactions = EquipmentFuelTransaction::query()
            ->with('equipment')
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->when($siteId, fn (Builder $query, string $id): Builder => $query->where('site_id', $id))
            ->where('transacted_at', '>=', now()->subDays(30))
            ->whereIn('status', [EquipmentFuelTransaction::STATUS_POSTED, EquipmentFuelTransaction::STATUS_REVERSED])
            ->get()
            ->filter(fn (EquipmentFuelTransaction $transaction): bool => Gate::forUser($user)->allows('view', $transaction->equipment));

        $openWorkOrders = EquipmentMaintenanceWorkOrder::query()
            ->with('equipment')
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->when($siteId, fn (Builder $query, string $id): Builder => $query->where('site_id', $id))
            ->whereIn('status', [
                EquipmentMaintenanceWorkOrder::STATUS_PLANNED,
                EquipmentMaintenanceWorkOrder::STATUS_APPROVED,
                EquipmentMaintenanceWorkOrder::STATUS_IN_PROGRESS,
            ])
            ->get()
            ->filter(fn (EquipmentMaintenanceWorkOrder $workOrder): bool => Gate::forUser($user)->allows('view', $workOrder));

        $reportLines = DailySiteReportEquipmentLine::query()
            ->with(['report', 'equipment'])
            ->where('tenant_id', $tenantId)
            ->whereHas('report', fn (Builder $query): Builder => $query
                ->where('project_id', $projectId)
                ->when($siteId, fn (Builder $query, string $id): Builder => $query->where('site_id', $id))
                ->where('status', DailySiteReport::STATUS_APPROVED))
            ->latest('created_at')
            ->get()
            ->filter(fn (DailySiteReportEquipmentLine $line): bool => $line->equipment === null
                || Gate::forUser($user)->allows('view', $line->equipment))
            ->values();

        $fuelByLine = EquipmentFuelTransaction::query()
            ->whereIn('daily_site_report_equipment_line_id', $reportLines->pluck('id'))
            ->get()
            ->keyBy('daily_site_report_equipment_line_id');
        $adjustmentsByLine = DsrEquipmentLineAdjustment::query()
            ->whereIn('daily_site_report_equipment_line_id', $reportLines->pluck('id'))
            ->get()
            ->toBase()
            ->groupBy('daily_site_report_equipment_line_id');

        return [
            'summary' => [
                'deployed' => $equipment->count(),
                'under_maintenance' => $equipment->where('current_status', 'under_maintenance')->count(),
                'out_of_service' => $equipment->where('current_status', 'out_of_service')->count(),
                'working_hours_30d' => $usageLogs->sum(fn (EquipmentUsageLog $log): float => (float) ($log->working_hours ?? 0)),
                'fuel_litres_30d' => $fuelTransactions->sum(fn (EquipmentFuelTransaction $transaction): float => (float) $transaction->quantity),
                'open_maintenance' => $openWorkOrders->count(),
                'dsr_posted' => $reportLines->where('fleet_posting_status', 'posted')->count(),
                'dsr_unposted' => $reportLines
                    ->whereNotNull('equipment_id')
                    ->whereNotIn('fleet_posting_status', ['posted', 'not_applicable'])
                    ->count(),
                'unlinked_snapshots' => $reportLines->whereNull('equipment_id')->count(),
                'fuel_exceptions' => $fuelByLine->where('exception_status', 'review_required')->count(),
                'fleet_adjustments' => $adjustmentsByLine->flatten(1)->count(),
            ],
            'equipment' => $equipment->map(fn (Equipment $asset): array => $this->equipmentRow($asset)),
            'reconciliation' => $reportLines
                ->take(20)
                ->map(fn (DailySiteReportEquipmentLine $line): array => $this->reconciliationRow($line, $fuelByLine, $adjustmentsByLine))
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function equipmentRow(Equipment $equipment): array
    {
        return [
            'id' => $equipment->id,
            'asset_code' => $equipment->asset_code,
            'name' => $equipment->name,
            'category_name' => $equipment->category->name,
            'current_status' => $equipment->current_status,
            'location_name' => $equipment->currentLocation?->name,
            'custodian_name' => $equipment->currentCustodian?->name,
            'meter_type' => $equipment->meter_type,
            'current_meter_reading' => $equipment->current_meter_reading,
            'condition_summary' => $equipment->condition_summary,
        ];
    }

    /**
     * @param  Collection<int|string, EquipmentFuelTransaction>  $fuelByLine
     * @param  Collection<int|string, Collection<int, DsrEquipmentLineAdjustment>>  $adjustmentsByLine
     * @return array<string, mixed>
     */
    private function reconciliationRow(
        DailySiteReportEquipmentLine $line,
        Collection $fuelByLine,
        Collection $adjustmentsByLine,
    ): array {
        /** @var EquipmentFuelTransaction|null $fuel */
        $fuel = $fuelByLine->get($line->id);
        /** @var Collection<int, DsrEquipmentLineAdjustment> $adjustments */
        $adjustments = $adjustmentsByLine->get($line->id, collect());
        $workingHours = (float) ($line->working_hours ?? 0)
            + $adjustments->sum(fn (DsrEquipmentLineAdjustment $adjustment): float => (float) $adjustment->working_hours_delta);
        $fuelQuantity = (float) ($line->fuel_quantity ?? 0)
            + $adjustments->sum(fn (DsrEquipmentLineAdjustment $adjustment): float => (float) $adjustment->fuel_quantity_delta);

        return [
            'id' => $line->id,
            'report_id' => $line->daily_site_report_id,
            'report_reference' => $line->report->reference,
            'report_date' => $line->report->report_date->toDateString(),
            'equipment_id' => $line->equipment?->id,
            'equipment_name' => $line->equipment_name,
            'equipment_identifier' => $line->equipment_identifier,
            'operating_status' => $line->status,
            'working_hours' => number_format($workingHours, 4, '.', ''),
            'fuel_quantity' => number_format($fuelQuantity, 4, '.', ''),
            'adjustment_count' => $adjustments->count(),
            'fleet_posting_status' => $line->equipment === null ? 'unlinked_snapshot' : $line->fleet_posting_status,
            'fuel_exception_status' => $fuel?->exception_status,
        ];
    }
}
