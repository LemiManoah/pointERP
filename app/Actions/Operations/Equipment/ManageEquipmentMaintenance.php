<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\BranchCurrency;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentMaintenancePartLine;
use App\Models\EquipmentMaintenanceSchedule;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EquipmentMaintenanceNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final readonly class ManageEquipmentMaintenance
{
    public function __construct(
        private RecordEquipmentMeterReading $recordMeterReading,
        private AuditLogger $auditLogger,
        private EquipmentMaintenanceNotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function saveSchedule(Equipment $equipment, array $data, User $actor, ?EquipmentMaintenanceSchedule $schedule = null): EquipmentMaintenanceSchedule
    {
        return DB::transaction(function () use ($actor, $data, $equipment, $schedule): EquipmentMaintenanceSchedule {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);
            if (! $equipment->is_active || $equipment->current_status === 'retired') {
                throw ValidationException::withMessages(['equipment' => 'Maintenance schedules cannot be changed for retired equipment.']);
            }
            if ($equipment->meter_type === 'none' && in_array($data['basis'], ['meter', 'whichever_first'], true)) {
                throw ValidationException::withMessages(['basis' => 'A no-meter asset can only use a date-based maintenance schedule.']);
            }

            $schedule ??= new EquipmentMaintenanceSchedule;
            $oldValues = $schedule->exists ? $schedule->toArray() : [];
            $lastDate = filled($data['last_service_date'] ?? null)
                ? CarbonImmutable::parse((string) $data['last_service_date'])
                : null;
            $lastReading = filled($data['last_service_reading'] ?? null)
                ? (float) $data['last_service_reading']
                : ($equipment->current_meter_reading === null ? null : (float) $equipment->current_meter_reading);
            if ($lastReading !== null && $equipment->current_meter_reading !== null && $lastReading > (float) $equipment->current_meter_reading) {
                throw ValidationException::withMessages(['last_service_reading' => 'The service baseline cannot exceed the latest accepted equipment reading.']);
            }
            $intervalDays = isset($data['interval_days']) ? (int) $data['interval_days'] : null;
            $intervalMeter = isset($data['interval_meter_units']) ? (float) $data['interval_meter_units'] : null;

            $schedule->fill([
                ...$data,
                'tenant_id' => $equipment->tenant_id,
                'equipment_id' => $equipment->id,
                'branch_id' => $equipment->branch_id,
                'last_service_date' => $lastDate,
                'last_service_reading' => $lastReading,
                'next_due_date' => $intervalDays === null ? null : ($lastDate ?? CarbonImmutable::today())->addDays($intervalDays),
                'next_due_reading' => $intervalMeter === null || $lastReading === null ? null : $lastReading + $intervalMeter,
                'created_by' => $schedule->exists ? $schedule->getAttribute('created_by') : $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $event = $oldValues === [] ? 'equipment.maintenance_schedule.created' : 'equipment.maintenance_schedule.updated';
            $this->auditLogger->record($event, $schedule, $actor, $oldValues, $schedule->refresh()->toArray());

            return $schedule->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function createWorkOrder(Equipment $equipment, array $data, User $actor): EquipmentMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($actor, $data, $equipment): EquipmentMaintenanceWorkOrder {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);
            if (! $equipment->is_active || in_array($equipment->current_status, ['retired', 'transferred'], true)) {
                throw ValidationException::withMessages(['equipment' => 'A maintenance work order cannot be opened for retired equipment or equipment in transit.']);
            }

            $schedule = isset($data['equipment_maintenance_schedule_id'])
                ? EquipmentMaintenanceSchedule::query()->where('equipment_id', $equipment->id)->where('is_active', true)->find($data['equipment_maintenance_schedule_id'])
                : null;
            if (isset($data['equipment_maintenance_schedule_id']) && ! $schedule instanceof EquipmentMaintenanceSchedule) {
                throw ValidationException::withMessages(['equipment_maintenance_schedule_id' => 'Select an active schedule belonging to this equipment.']);
            }

            $provider = isset($data['provider_customer_id'])
                ? Customer::query()->where('tenant_id', $equipment->tenant_id)->where('status', 'active')->find($data['provider_customer_id'])
                : null;
            $workOrder = EquipmentMaintenanceWorkOrder::query()->create([
                ...$data,
                'tenant_id' => $equipment->tenant_id,
                'equipment_id' => $equipment->id,
                'branch_id' => $equipment->branch_id,
                'project_id' => $equipment->current_project_id,
                'site_id' => $equipment->current_site_id,
                'equipment_location_id' => $equipment->current_location_id,
                'provider_customer_id' => $provider?->id,
                'provider_name' => $provider?->name ?? ($data['provider_name'] ?? null),
                'status' => EquipmentMaintenanceWorkOrder::STATUS_PLANNED,
                'requested_by' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->auditLogger->record('equipment.maintenance_work_order.created', $workOrder, $actor, [], $workOrder->toArray());
            DB::afterCommit(fn () => $this->notifications->created($workOrder));

            return $workOrder->refresh();
        });
    }

    public function approve(EquipmentMaintenanceWorkOrder $workOrder, ?string $note, User $actor): EquipmentMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($actor, $note, $workOrder): EquipmentMaintenanceWorkOrder {
            $workOrder = EquipmentMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if ($workOrder->status !== EquipmentMaintenanceWorkOrder::STATUS_PLANNED) {
                throw ValidationException::withMessages(['work_order' => 'Only a planned work order can be approved.']);
            }
            $workOrder->forceFill(['status' => EquipmentMaintenanceWorkOrder::STATUS_APPROVED, 'approved_by' => $actor->id, 'updated_by' => $actor->id])->save();
            $this->auditLogger->record('equipment.maintenance_work_order.approved', $workOrder, $actor, ['status' => 'planned'], ['status' => 'approved'], $note);
            DB::afterCommit(fn () => $this->notifications->changed($workOrder, 'success', 'Maintenance work order approved'));

            return $workOrder->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function start(EquipmentMaintenanceWorkOrder $workOrder, array $data, User $actor): EquipmentMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($actor, $data, $workOrder): EquipmentMaintenanceWorkOrder {
            $workOrder = EquipmentMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($workOrder->equipment_id);
            if ($workOrder->status !== EquipmentMaintenanceWorkOrder::STATUS_APPROVED) {
                throw ValidationException::withMessages(['work_order' => 'Only an approved work order can be started.']);
            }
            if (in_array($equipment->current_status, ['retired', 'transferred', 'under_maintenance'], true)) {
                throw ValidationException::withMessages(['equipment' => 'This equipment is unavailable for maintenance start.']);
            }

            $workOrder->forceFill([
                'status' => EquipmentMaintenanceWorkOrder::STATUS_IN_PROGRESS,
                'prior_equipment_status' => $equipment->current_status,
                'actual_start_at' => CarbonImmutable::parse((string) $data['actual_start_at']),
                'opening_meter_reading' => $data['opening_meter_reading'] ?? $equipment->current_meter_reading,
                'supervised_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();
            $equipment->forceFill(['current_status' => 'under_maintenance', 'updated_by' => $actor->id])->save();
            $this->auditLogger->record('equipment.maintenance_work_order.started', $workOrder, $actor, ['status' => 'approved'], ['status' => 'in_progress', 'equipment_status' => 'under_maintenance']);
            DB::afterCommit(fn () => $this->notifications->changed($workOrder, 'warning', 'Equipment maintenance started'));

            return $workOrder->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function complete(EquipmentMaintenanceWorkOrder $workOrder, array $data, User $actor): EquipmentMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($actor, $data, $workOrder): EquipmentMaintenanceWorkOrder {
            $workOrder = EquipmentMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($workOrder->equipment_id);
            if ($workOrder->status !== EquipmentMaintenanceWorkOrder::STATUS_IN_PROGRESS || $workOrder->actual_start_at === null) {
                throw ValidationException::withMessages(['work_order' => 'Only an in-progress work order can be completed.']);
            }
            $completedAt = CarbonImmutable::parse((string) $data['completed_at']);
            if ($completedAt->lt($workOrder->actual_start_at)) {
                throw ValidationException::withMessages(['completed_at' => 'Completion cannot be earlier than maintenance start.']);
            }
            if ($equipment->meter_type !== 'none' && blank($data['closing_meter_reading'] ?? null)) {
                throw ValidationException::withMessages(['closing_meter_reading' => 'A completion meter reading is required for metered equipment.']);
            }

            $partRows = is_array($data['parts'] ?? null) ? $data['parts'] : [];
            $hasCosts = filled($data['labour_cost'] ?? null) || filled($data['other_cost'] ?? null)
                || collect($partRows)->contains(fn (mixed $part): bool => is_array($part) && filled($part['unit_cost'] ?? null));
            if ($hasCosts && ! $actor->can('equipment.costs.view')) {
                throw ValidationException::withMessages(['costs' => 'You do not have permission to record maintenance costs.']);
            }
            $currency = $hasCosts ? mb_strtoupper((string) ($data['currency_code'] ?? '')) : null;
            if ($currency !== null && ! BranchCurrency::query()->where('branch_id', $workOrder->branch_id)->where('currency_code', $currency)->where('is_enabled', true)->exists()) {
                throw ValidationException::withMessages(['currency_code' => 'Select a currency enabled for this branch.']);
            }

            $partsCost = 0.0;
            foreach ($partRows as $part) {
                if (! is_array($part)) continue;
                $unitCost = filled($part['unit_cost'] ?? null) ? (float) $part['unit_cost'] : null;
                $total = $unitCost === null ? null : (float) $part['quantity'] * $unitCost;
                $partsCost += $total ?? 0.0;
                EquipmentMaintenancePartLine::query()->create([
                    ...$part,
                    'tenant_id' => $workOrder->tenant_id,
                    'equipment_maintenance_work_order_id' => $workOrder->id,
                    'unit_cost' => $unitCost,
                    'total_cost' => $total,
                    'currency_code' => $unitCost === null ? null : $currency,
                ]);
            }

            if ($equipment->meter_type !== 'none') {
                $this->recordMeterReading->handle($equipment, [
                    'reading_value' => $data['closing_meter_reading'], 'read_at' => $completedAt,
                    'project_id' => $workOrder->project_id, 'site_id' => $workOrder->site_id,
                    'equipment_location_id' => $workOrder->equipment_location_id,
                    'event_type' => 'maintenance', 'evidence_note' => 'Completed maintenance work order '.$workOrder->reference,
                ], $actor);
            }

            $labour = (float) ($data['labour_cost'] ?? 0);
            $other = (float) ($data['other_cost'] ?? 0);
            $schedule = $workOrder->schedule;
            $nextDate = $schedule?->interval_days === null ? null : $completedAt->addDays($schedule->interval_days)->toDateString();
            $closingReading = $data['closing_meter_reading'] ?? null;
            $nextReading = $schedule?->interval_meter_units === null || $closingReading === null
                ? null : (float) $closingReading + (float) $schedule->interval_meter_units;
            $releaseStatus = $workOrder->prior_equipment_status === 'out_of_service' ? 'out_of_service' : ($workOrder->prior_equipment_status ?? 'available');
            $completionData = Arr::except($data, ['parts']);

            $workOrder->forceFill([
                ...$completionData,
                'status' => EquipmentMaintenanceWorkOrder::STATUS_COMPLETED,
                'completed_at' => $completedAt,
                'parts_cost' => $hasCosts ? $partsCost : null,
                'total_cost' => $hasCosts ? $labour + $partsCost + $other : null,
                'currency_code' => $currency,
                'next_service_date' => $nextDate,
                'next_service_reading' => $nextReading,
                'completed_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();
            if ($schedule instanceof EquipmentMaintenanceSchedule) {
                $schedule->forceFill([
                    'last_service_date' => $completedAt->toDateString(), 'last_service_reading' => $closingReading,
                    'next_due_date' => $nextDate, 'next_due_reading' => $nextReading, 'updated_by' => $actor->id,
                ])->save();
            }
            $equipment->forceFill(['current_status' => $releaseStatus, 'updated_by' => $actor->id])->save();
            $this->auditLogger->record('equipment.maintenance_work_order.completed', $workOrder, $actor, ['status' => 'in_progress'], ['status' => 'completed', 'equipment_status' => $releaseStatus, 'total_cost' => $workOrder->total_cost]);
            DB::afterCommit(fn () => $this->notifications->changed($workOrder, 'success', 'Equipment maintenance completed'));

            return $workOrder->refresh();
        });
    }

    public function cancel(EquipmentMaintenanceWorkOrder $workOrder, string $reason, string $releaseStatus, User $actor): EquipmentMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($actor, $reason, $releaseStatus, $workOrder): EquipmentMaintenanceWorkOrder {
            $workOrder = EquipmentMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($workOrder->equipment_id);
            if (! in_array($workOrder->status, [EquipmentMaintenanceWorkOrder::STATUS_PLANNED, EquipmentMaintenanceWorkOrder::STATUS_APPROVED, EquipmentMaintenanceWorkOrder::STATUS_IN_PROGRESS], true)) {
                throw ValidationException::withMessages(['work_order' => 'This work order can no longer be cancelled.']);
            }
            $oldStatus = $workOrder->status;
            if ($oldStatus === EquipmentMaintenanceWorkOrder::STATUS_IN_PROGRESS) {
                $hasActiveAssignment = $equipment->assignments()->where('status', 'active')->exists();
                if (($releaseStatus === 'assigned') !== $hasActiveAssignment && $releaseStatus !== 'out_of_service') {
                    throw ValidationException::withMessages(['release_status' => $hasActiveAssignment
                        ? 'Equipment with active custody must return to Assigned or be marked Out of Service.'
                        : 'Equipment cannot be released as Assigned without an active assignment.']);
                }
                $equipment->forceFill(['current_status' => $releaseStatus, 'updated_by' => $actor->id])->save();
            }
            $workOrder->forceFill([
                'status' => EquipmentMaintenanceWorkOrder::STATUS_CANCELLED,
                'cancellation_reason' => $reason, 'cancelled_at' => now(),
                'cancelled_by' => $actor->id, 'updated_by' => $actor->id,
            ])->save();
            $this->auditLogger->record('equipment.maintenance_work_order.cancelled', $workOrder, $actor, ['status' => $oldStatus], ['status' => 'cancelled', 'equipment_status' => $equipment->current_status], $reason);
            DB::afterCommit(fn () => $this->notifications->changed($workOrder, 'warning', 'Maintenance work order cancelled'));

            return $workOrder->refresh();
        });
    }
}
