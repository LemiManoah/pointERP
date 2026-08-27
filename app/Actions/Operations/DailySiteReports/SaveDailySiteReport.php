<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Enums\DsrMaterialReconciliationStatus;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCostLine;
use App\Models\DailySiteReportDelayLine;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\DailySiteReportLabourLine;
use App\Models\DailySiteReportMaterialLine;
use App\Models\DailySiteReportWorkLine;
use App\Models\Equipment;
use App\Models\ExpectedDailySiteReport;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryQuantityConverter;
use App\Services\ReportingCalendarResolver;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveDailySiteReport
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuditLogger $auditLogger,
        private ReportingCalendarResolver $calendarResolver,
        private InventoryQuantityConverter $quantityConverter,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor, ?DailySiteReport $report = null): DailySiteReport
    {
        return DB::transaction(function () use ($actor, $data, $report): DailySiteReport {
            $site = Site::query()->with('project')->findOrFail($data['site_id']);
            $oldValues = $report?->fresh()?->toArray() ?? [];
            $report ??= new DailySiteReport();

            if ($report->exists && $report->isApproved()) {
                throw ValidationException::withMessages([
                    'report' => 'Approved daily site reports are locked. Create a correction instead.',
                ]);
            }

            $report->fill([
                'tenant_id' => $this->tenantContext->id(),
                'branch_id' => $site->branch_id,
                'project_id' => $site->project_id,
                'site_id' => $site->id,
                'report_date' => $data['report_date'],
                'reference' => $data['reference'] ?? $this->reference($site, (string) $data['report_date']),
                'weather' => $data['weather'] ?? null,
                'site_conditions' => $data['site_conditions'] ?? null,
                'work_summary' => $data['work_summary'] ?? null,
                'delay_summary' => $data['delay_summary'] ?? null,
                'visitor_summary' => $data['visitor_summary'] ?? null,
                'hse_notes' => $data['hse_notes'] ?? null,
                'environment_notes' => $data['environment_notes'] ?? null,
                'social_notes' => $data['social_notes'] ?? null,
                'completion_percent' => $data['completion_percent'] ?? null,
                'status' => $report->status === DailySiteReport::STATUS_MISSING || ! $report->exists
                    ? DailySiteReport::STATUS_DRAFT
                    : $report->status,
                'created_by' => $report->exists ? $report->created_by : $actor->id,
                'updated_by' => $actor->id,
            ]);

            $report->save();

            $outputValue = $this->syncLines(
                $report,
                DailySiteReportWorkLine::class,
                $this->normalizeWorkLines($report, $data['work_lines'] ?? []),
            );
            $labourCost = $this->syncLines($report, DailySiteReportLabourLine::class, $data['labour_lines'] ?? []);
            $equipmentCost = $this->syncLines(
                $report,
                DailySiteReportEquipmentLine::class,
                $this->normalizeEquipmentLines($report, $data['equipment_lines'] ?? []),
            );
            $materialCost = $this->syncLines($report, DailySiteReportMaterialLine::class, $this->normalizeMaterialLines($report, $data['material_lines'] ?? []));
            $otherCost = $this->syncLines($report, DailySiteReportCostLine::class, $data['cost_lines'] ?? []);
            $this->syncLines($report, DailySiteReportDelayLine::class, $data['delay_lines'] ?? []);

            $inputCost = $labourCost + $equipmentCost + $materialCost + $otherCost;

            $report->forceFill([
                'output_value' => $outputValue,
                'input_cost' => $inputCost,
                'profit_loss' => $outputValue - $inputCost,
            ])->save();

            $event = $oldValues === []
                ? 'operations.daily_site_report.created'
                : 'operations.daily_site_report.updated';

            $this->syncExpectedReport($report, $actor);

            $this->auditLogger->record($event, $report, $actor, $oldValues, $report->fresh()?->toArray() ?? []);

            return $report;
        });
    }

    private function syncExpectedReport(DailySiteReport $report, User $actor): void
    {
        $site = $report->site;

        if (! $site instanceof Site) {
            return;
        }

        $expected = ExpectedDailySiteReport::query()->firstOrNew([
            'tenant_id' => $report->tenant_id,
            'site_id' => $report->site_id,
            'report_date' => $report->report_date->copy()->startOfDay(),
        ]);

        $expected->fill([
            'branch_id' => $report->branch_id,
            'project_id' => $report->project_id,
            'deadline_at' => $expected->deadline_at ?? $this->deadlineAt($site, $report),
            'status' => ExpectedDailySiteReport::STATUS_EXPECTED,
            'daily_site_report_id' => $report->id,
            'marked_by' => $actor->id,
            'marked_at' => now(),
        ])->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeWorkLines(DailySiteReport $report, mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        return collect($lines)
            ->filter(fn (mixed $line): bool => is_array($line))
            ->map(function (array $line) use ($report): array {
                $activityId = $line['project_activity_id'] ?? null;

                if (! is_string($activityId) || $activityId === '') {
                    return $line;
                }

                $activity = ProjectActivity::query()
                    ->whereKey($activityId)
                    ->where('tenant_id', $report->tenant_id)
                    ->where('project_id', $report->project_id)
                    ->where('status', 'active')
                    ->where(function (Builder $query) use ($report): void {
                        $query->whereNull('site_id')->orWhere('site_id', $report->site_id);
                    })
                    ->first();

                if (! $activity instanceof ProjectActivity) {
                    throw ValidationException::withMessages([
                        'work_lines' => 'A selected BOQ activity is not available for this report site.',
                    ]);
                }

                return [
                    ...$line,
                    'boq_item_number' => $activity->boq_item_number,
                    'description' => $activity->name,
                    'unit' => $activity->unit,
                    'rate_amount' => $activity->rate_amount,
                    'currency_code' => $activity->currency_code,
                ];
            })
            ->values()
            ->all();
    }

    private function deadlineAt(Site $site, DailySiteReport $report): CarbonInterface
    {
        return $this->calendarResolver->deadlineAt($site, $report->report_date);
    }

    /** @return list<array<string, mixed>> */
    private function normalizeMaterialLines(DailySiteReport $report, mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        return collect($lines)->filter(fn (mixed $line): bool => is_array($line))->map(function (array $line) use ($report): array {
            $itemId = $line['inventory_item_id'] ?? null;
            if (! is_string($itemId) || $itemId === '') {
                return [...$line, 'inventory_item_id' => null, 'inventory_store_id' => null, 'unit_of_measure_id' => null, 'conversion_multiplier' => null, 'stock_unit_quantity' => null, 'inventory_reconciliation_status' => DsrMaterialReconciliationStatus::NotLinked->value];
            }

            $item = InventoryItem::query()->where('is_active', true)->with('stockUnit')->findOrFail($itemId);
            $unitId = $line['unit_of_measure_id'] ?? $item->stock_unit_id;
            $unit = UnitOfMeasure::query()->where('is_active', true)->findOrFail($unitId);
            $storeId = $line['inventory_store_id'] ?? null;
            if (is_string($storeId) && $storeId !== '') {
                $store = InventoryStore::query()->where('branch_id', $report->branch_id)->where('is_active', true)->findOrFail($storeId);
                if (! InventoryStoreItem::query()->where('inventory_store_id', $store->id)->where('inventory_item_id', $item->id)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['material_lines' => $item->name.' is not enabled in the selected store.']);
                }
            }

            $multiplier = $this->quantityConverter->multiplier($item, $unit->id);
            $quantity = BigDecimal::of((string) ($line['quantity'] ?? 0));

            return [
                ...$line, 'inventory_item_id' => $item->id, 'inventory_store_id' => is_string($storeId) && $storeId !== '' ? $storeId : null,
                'unit_of_measure_id' => $unit->id, 'conversion_multiplier' => (string) $multiplier->toScale(10),
                'stock_unit_quantity' => (string) $quantity->multipliedBy($multiplier)->toScale(4),
                'inventory_reconciliation_status' => DsrMaterialReconciliationStatus::Pending->value,
                'material_name' => $item->name, 'unit' => $unit->symbol ?? $unit->name,
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function normalizeEquipmentLines(DailySiteReport $report, mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        return collect($lines)
            ->filter(fn (mixed $line): bool => is_array($line))
            ->map(function (array $line) use ($report): array {
                $equipmentId = $line['equipment_id'] ?? null;
                if (! is_string($equipmentId) || $equipmentId === '') {
                    return $line;
                }

                $equipment = Equipment::query()
                    ->whereKey($equipmentId)
                    ->where('tenant_id', $report->tenant_id)
                    ->where('branch_id', $report->branch_id)
                    ->where('is_active', true)
                    ->first();
                if (! $equipment instanceof Equipment) {
                    throw ValidationException::withMessages(['equipment_lines' => 'Select active equipment belonging to the report branch.']);
                }

                if (is_numeric($line['opening_meter_reading'] ?? null)
                    && is_numeric($line['closing_meter_reading'] ?? null)
                    && (float) $line['closing_meter_reading'] < (float) $line['opening_meter_reading']) {
                    throw ValidationException::withMessages(['equipment_lines' => 'A closing meter reading cannot be below its opening reading.']);
                }

                return [
                    ...$line,
                    'equipment_name' => $equipment->name,
                    'equipment_identifier' => $equipment->asset_code,
                    'fleet_posting_status' => 'unposted',
                    'fleet_posted_at' => null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  class-string<DailySiteReportWorkLine|DailySiteReportLabourLine|DailySiteReportEquipmentLine|DailySiteReportMaterialLine|DailySiteReportCostLine|DailySiteReportDelayLine>  $modelClass
     */
    private function syncLines(DailySiteReport $report, string $modelClass, mixed $lines): float
    {
        $existingLines = $modelClass::query()
            ->where('daily_site_report_id', $report->id)
            ->get()
            ->keyBy('id');

        $modelClass::query()
            ->where('daily_site_report_id', $report->id)
            ->delete();

        if (! is_array($lines)) {
            return 0.0;
        }

        $total = 0.0;

        foreach (array_values($lines) as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $existingLine = isset($line['id']) ? $existingLines->get($line['id']) : null;

            foreach (['rate_amount', 'amount', 'currency_code'] as $preservedField) {
                if (! array_key_exists($preservedField, $line) && $existingLine instanceof Model) {
                    $line[$preservedField] = $existingLine->getAttribute($preservedField);
                }
            }

            $amount = $this->amount($line);
            $total += $amount;

            $modelClass::query()->create([
                ...$line,
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'daily_site_report_id' => $report->id,
                'amount' => $amount === 0.0 ? ($line['amount'] ?? null) : $amount,
                'sort_order' => $line['sort_order'] ?? $index,
            ]);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function amount(array $line): float
    {
        if (is_numeric($line['amount'] ?? null)) {
            return (float) $line['amount'];
        }

        if (is_numeric($line['quantity'] ?? null) && is_numeric($line['rate_amount'] ?? null)) {
            return (float) $line['quantity'] * (float) $line['rate_amount'];
        }

        if (is_numeric($line['hours'] ?? null) && is_numeric($line['rate_amount'] ?? null)) {
            return (float) $line['hours'] * (float) $line['rate_amount'];
        }

        if (is_numeric($line['working_hours'] ?? null) && is_numeric($line['rate_amount'] ?? null)) {
            return (float) $line['working_hours'] * (float) $line['rate_amount'];
        }

        return 0.0;
    }

    private function reference(Site $site, string $reportDate): string
    {
        return 'DSR-'.$site->reference.'-'.str_replace('-', '', $reportDate);
    }
}
