<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Actions\Operations\Equipment\RecordEquipmentMeterReading;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\Equipment;
use App\Models\EquipmentFuelTransaction;
use App\Models\EquipmentMeterReading;
use App\Models\EquipmentUsageLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EquipmentFuelExceptionEvaluator;
use App\Services\EquipmentFuelNotificationService;
use Illuminate\Validation\ValidationException;

final readonly class PostApprovedDsrEquipmentLines
{
    public function __construct(
        private RecordEquipmentMeterReading $recordMeterReading,
        private EquipmentFuelExceptionEvaluator $fuelEvaluator,
        private EquipmentFuelNotificationService $fuelNotifications,
        private AuditLogger $auditLogger,
    ) {}

    public function handle(DailySiteReport $report, User $actor): void
    {
        $report->loadMissing('equipmentLines');

        foreach ($report->equipmentLines as $line) {
            if ($line->equipment_id === null) {
                continue;
            }

            $this->postLine($report, $line, $actor);
        }
    }

    private function postLine(DailySiteReport $report, DailySiteReportEquipmentLine $line, User $actor): void
    {
        $line = DailySiteReportEquipmentLine::query()->lockForUpdate()->findOrFail($line->id);
        if ($line->fleet_posting_status === 'posted') {
            return;
        }

        $equipment = Equipment::query()->lockForUpdate()->findOrFail($line->equipment_id);
        if ($equipment->tenant_id !== $report->tenant_id || $equipment->branch_id !== $report->branch_id) {
            throw ValidationException::withMessages(['equipment_lines' => 'A selected asset is outside the report tenant or responsible branch.']);
        }

        $evaluation = $this->fuelEvaluator->evaluate($equipment, $line);
        EquipmentUsageLog::query()->firstOrCreate(
            ['daily_site_report_equipment_line_id' => $line->id],
            [
                'tenant_id' => $report->tenant_id, 'equipment_id' => $equipment->id,
                'branch_id' => $report->branch_id, 'project_id' => $report->project_id,
                'site_id' => $report->site_id, 'equipment_location_id' => $equipment->current_location_id,
                'usage_date' => $report->report_date, 'operating_status' => $line->status,
                'opening_meter_reading' => $line->opening_meter_reading,
                'closing_meter_reading' => $line->closing_meter_reading,
                'meter_usage' => $evaluation['meter_usage'], 'working_hours' => $line->working_hours,
                'idle_hours' => $line->idle_hours, 'notes' => $line->notes,
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now(),
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ],
        );

        $this->postMeterReading($report, $line, $equipment, $actor);
        $fuelTransaction = $this->postFuel($report, $line, $equipment, $evaluation, $actor);

        $line->forceFill(['fleet_posting_status' => 'posted', 'fleet_posted_at' => now()])->save();
        $this->auditLogger->record('equipment.dsr_line.posted', $line, $actor, [], [
            'equipment_id' => $equipment->id,
            'usage_date' => $report->report_date->toDateString(),
            'fuel_transaction_id' => $fuelTransaction?->id,
            'exception_status' => $evaluation['status'],
        ]);

        if ($fuelTransaction instanceof EquipmentFuelTransaction && $evaluation['status'] === 'review_required') {
            $this->fuelNotifications->exception($fuelTransaction);
        }
    }

    private function postMeterReading(DailySiteReport $report, DailySiteReportEquipmentLine $line, Equipment $equipment, User $actor): void
    {
        if ($equipment->meter_type === 'none' || ! is_numeric($line->closing_meter_reading)) {
            return;
        }

        if (EquipmentMeterReading::query()->where('source_type', DailySiteReportEquipmentLine::class)->where('source_id', $line->id)->exists()) {
            return;
        }

        $readAt = $report->report_date->copy()->endOfDay();
        $latest = $equipment->meterReadings()->where('status', EquipmentMeterReading::STATUS_ACCEPTED)->latest('read_at')->first();
        if ($readAt->isFuture() || ($latest instanceof EquipmentMeterReading && ($readAt->lt($latest->read_at) || (float) $line->closing_meter_reading < (float) $latest->reading_value))) {
            return;
        }

        $this->recordMeterReading->handle($equipment, [
            'reading_value' => $line->closing_meter_reading, 'read_at' => $readAt,
            'branch_id' => $report->branch_id, 'project_id' => $report->project_id,
            'site_id' => $report->site_id, 'equipment_location_id' => $equipment->current_location_id,
            'event_type' => 'daily_log', 'source_type' => DailySiteReportEquipmentLine::class,
            'source_id' => $line->id, 'evidence_note' => $line->evidence_note ?? 'Approved DSR '.$report->reference,
        ], $actor);
    }

    /** @param array{status: string, reason: string, actual_rate: string|null, evidence_basis: string, meter_usage: string|null} $evaluation */
    private function postFuel(DailySiteReport $report, DailySiteReportEquipmentLine $line, Equipment $equipment, array $evaluation, User $actor): ?EquipmentFuelTransaction
    {
        if (! is_numeric($line->fuel_quantity) || (float) $line->fuel_quantity <= 0) {
            return null;
        }

        return EquipmentFuelTransaction::query()->firstOrCreate(
            ['daily_site_report_equipment_line_id' => $line->id],
            [
                'tenant_id' => $report->tenant_id, 'equipment_id' => $equipment->id,
                'branch_id' => $report->branch_id, 'project_id' => $report->project_id,
                'site_id' => $report->site_id, 'equipment_location_id' => $equipment->current_location_id,
                'transacted_at' => $report->report_date->isToday() ? now() : $report->report_date->copy()->endOfDay(),
                'transaction_type' => $line->fuel_transaction_type ?? 'consumption',
                'fuel_type' => $line->fuel_type ?? 'diesel', 'quantity' => $line->fuel_quantity,
                'unit' => 'litre', 'source_type' => 'other', 'source_name' => 'Approved DSR '.$report->reference,
                'meter_reading' => $line->closing_meter_reading, 'is_full_tank' => false,
                'voucher_reference' => $report->reference, 'notes' => $line->evidence_note ?? $line->notes,
                'exception_status' => $evaluation['status'], 'exception_reason' => $evaluation['reason'],
                'status' => EquipmentFuelTransaction::STATUS_POSTED,
                'submitted_by' => $report->submitted_by ?? $actor->id, 'submitted_at' => $report->submitted_at ?? now(),
                'approved_by' => $actor->id, 'approved_at' => now(), 'posted_by' => $actor->id,
                'posted_at' => now(), 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ],
        );
    }
}
