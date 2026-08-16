<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\DsrEquipmentLineAdjustment;
use App\Models\Equipment;
use App\Models\EquipmentFuelTransaction;
use App\Models\EquipmentUsageLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

final readonly class ApplyDsrEquipmentLineAdjustments
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  list<array<string, mixed>>  $adjustments
     */
    public function handle(
        DailySiteReportCorrection $correction,
        DailySiteReport $report,
        User $actor,
        array $adjustments,
    ): void {
        foreach ($adjustments as $data) {
            $this->applyLine($correction, $report, $actor, $data);
        }
    }

    /** @param array<string, mixed> $data */
    private function applyLine(
        DailySiteReportCorrection $correction,
        DailySiteReport $report,
        User $actor,
        array $data,
    ): void {
        $lineId = (string) ($data['line_id'] ?? '');
        $existing = DsrEquipmentLineAdjustment::query()
            ->where('daily_site_report_correction_id', $correction->id)
            ->where('daily_site_report_equipment_line_id', $lineId)
            ->first();

        if ($existing instanceof DsrEquipmentLineAdjustment) {
            return;
        }

        $line = DailySiteReportEquipmentLine::query()
            ->where('daily_site_report_id', $report->id)
            ->lockForUpdate()
            ->findOrFail($lineId);

        if ($line->equipment_id === null || $line->fleet_posting_status !== 'posted') {
            throw ValidationException::withMessages([
                'changes.equipment_adjustments' => 'Only linked equipment lines already posted to the fleet ledger can be adjusted.',
            ]);
        }

        $equipment = Equipment::query()->lockForUpdate()->findOrFail($line->equipment_id);
        if ($equipment->tenant_id !== $report->tenant_id || $equipment->branch_id !== $report->branch_id) {
            throw ValidationException::withMessages([
                'changes.equipment_adjustments' => 'The equipment correction is outside the report tenant or branch.',
            ]);
        }

        $workingDelta = (float) ($data['working_hours_delta'] ?? 0);
        $idleDelta = (float) ($data['idle_hours_delta'] ?? 0);
        $fuelDelta = (float) ($data['fuel_quantity_delta'] ?? 0);
        if ($workingDelta === 0.0 && $idleDelta === 0.0 && $fuelDelta === 0.0) {
            throw ValidationException::withMessages([
                'changes.equipment_adjustments' => 'Enter at least one non-zero equipment adjustment.',
            ]);
        }

        $reason = filled($data['note'] ?? null)
            ? $correction->reason.' '.$data['note']
            : $correction->reason;
        $usageLog = $this->appendUsage($correction, $report, $line, $actor, $workingDelta, $idleDelta, $reason);
        $fuelTransaction = $this->appendFuel($correction, $report, $line, $actor, $fuelDelta, $reason);

        $adjustment = DsrEquipmentLineAdjustment::query()->create([
            'tenant_id' => $report->tenant_id,
            'branch_id' => $report->branch_id,
            'daily_site_report_correction_id' => $correction->id,
            'daily_site_report_equipment_line_id' => $line->id,
            'equipment_id' => $equipment->id,
            'working_hours_delta' => number_format($workingDelta, 4, '.', ''),
            'idle_hours_delta' => number_format($idleDelta, 4, '.', ''),
            'fuel_quantity_delta' => number_format($fuelDelta, 4, '.', ''),
            'reason' => $reason,
            'equipment_usage_log_id' => $usageLog?->id,
            'equipment_fuel_transaction_id' => $fuelTransaction?->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $this->auditLogger->record(
            'equipment.dsr_line.adjusted',
            $adjustment,
            $actor,
            [],
            [
                'daily_site_report_id' => $report->id,
                'daily_site_report_equipment_line_id' => $line->id,
                'equipment_id' => $equipment->id,
                'working_hours_delta' => $adjustment->working_hours_delta,
                'idle_hours_delta' => $adjustment->idle_hours_delta,
                'fuel_quantity_delta' => $adjustment->fuel_quantity_delta,
                'equipment_usage_log_id' => $usageLog?->id,
                'equipment_fuel_transaction_id' => $fuelTransaction?->id,
            ],
            $reason,
        );
    }

    private function appendUsage(
        DailySiteReportCorrection $correction,
        DailySiteReport $report,
        DailySiteReportEquipmentLine $line,
        User $actor,
        float $workingDelta,
        float $idleDelta,
        string $reason,
    ): ?EquipmentUsageLog {
        if ($workingDelta === 0.0 && $idleDelta === 0.0) {
            return null;
        }

        $original = EquipmentUsageLog::query()
            ->where('daily_site_report_equipment_line_id', $line->id)
            ->lockForUpdate()
            ->firstOrFail();
        $prior = DsrEquipmentLineAdjustment::query()
            ->where('daily_site_report_equipment_line_id', $line->id)
            ->get();
        $currentWorking = (float) ($original->working_hours ?? 0)
            + $prior->sum(fn (DsrEquipmentLineAdjustment $item): float => (float) $item->working_hours_delta);
        $currentIdle = (float) ($original->idle_hours ?? 0)
            + $prior->sum(fn (DsrEquipmentLineAdjustment $item): float => (float) $item->idle_hours_delta);

        if ($currentWorking + $workingDelta < 0 || $currentIdle + $idleDelta < 0) {
            throw ValidationException::withMessages([
                'changes.equipment_adjustments' => 'Corrected working and idle hours cannot become negative.',
            ]);
        }

        return EquipmentUsageLog::query()->create([
            'tenant_id' => $report->tenant_id,
            'equipment_id' => $line->equipment_id,
            'branch_id' => $report->branch_id,
            'project_id' => $report->project_id,
            'site_id' => $report->site_id,
            'usage_date' => $report->report_date,
            'operating_status' => 'adjustment',
            'working_hours' => number_format($workingDelta, 4, '.', ''),
            'idle_hours' => number_format($idleDelta, 4, '.', ''),
            'notes' => sprintf('DSR correction %s: %s', $correction->id, $reason),
            'status' => 'posted',
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function appendFuel(
        DailySiteReportCorrection $correction,
        DailySiteReport $report,
        DailySiteReportEquipmentLine $line,
        User $actor,
        float $fuelDelta,
        string $reason,
    ): ?EquipmentFuelTransaction {
        if ($fuelDelta === 0.0) {
            return null;
        }

        $original = EquipmentFuelTransaction::query()
            ->where('daily_site_report_equipment_line_id', $line->id)
            ->lockForUpdate()
            ->first();
        if ($original instanceof EquipmentFuelTransaction && $original->status === EquipmentFuelTransaction::STATUS_REVERSED) {
            throw ValidationException::withMessages([
                'changes.equipment_adjustments' => 'A reversed DSR fuel entry cannot receive another correction.',
            ]);
        }

        $priorFuel = DsrEquipmentLineAdjustment::query()
            ->where('daily_site_report_equipment_line_id', $line->id)
            ->sum('fuel_quantity_delta');
        $originalQuantity = $original instanceof EquipmentFuelTransaction
            ? (float) $original->quantity
            : 0.0;
        $currentFuel = $originalQuantity + (float) $priorFuel;
        if ($currentFuel + $fuelDelta < 0) {
            throw ValidationException::withMessages([
                'changes.equipment_adjustments' => 'Corrected fuel quantity cannot become negative.',
            ]);
        }

        $unitCost = $original instanceof EquipmentFuelTransaction ? $original->unit_cost : null;
        $fuelType = $original instanceof EquipmentFuelTransaction
            ? $original->fuel_type
            : ($line->fuel_type ?? 'diesel');
        $currencyCode = $original instanceof EquipmentFuelTransaction ? $original->currency_code : null;

        return EquipmentFuelTransaction::query()->create([
            'tenant_id' => $report->tenant_id,
            'equipment_id' => $line->equipment_id,
            'branch_id' => $report->branch_id,
            'project_id' => $report->project_id,
            'site_id' => $report->site_id,
            'transacted_at' => $report->report_date->isToday() ? now() : $report->report_date->copy()->endOfDay(),
            'transaction_type' => 'adjustment',
            'fuel_type' => $fuelType,
            'quantity' => number_format($fuelDelta, 4, '.', ''),
            'unit' => 'litre',
            'source_type' => 'other',
            'source_name' => 'Approved DSR correction '.$correction->id,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost === null ? null : number_format($fuelDelta * (float) $unitCost, 4, '.', ''),
            'currency_code' => $currencyCode,
            'is_full_tank' => false,
            'voucher_reference' => $report->reference,
            'notes' => $reason,
            'exception_status' => 'not_evaluated',
            'exception_reason' => 'Additive quantity correction approved through DSR correction control.',
            'status' => EquipmentFuelTransaction::STATUS_POSTED,
            'submitted_by' => $correction->requested_by,
            'submitted_at' => $correction->created_at,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
