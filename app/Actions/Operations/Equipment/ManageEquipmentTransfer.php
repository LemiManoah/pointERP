<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Branch;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentLocation;
use App\Models\EquipmentTransfer;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\EquipmentTransferNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ManageEquipmentTransfer
{
    public function __construct(
        private RecordEquipmentMeterReading $recordMeterReading,
        private AuditLogger $auditLogger,
        private BranchContext $branchContext,
        private EquipmentTransferNotificationService $notificationService,
    ) {}

    /** @param array<string, mixed> $data */
    public function request(Equipment $equipment, array $data, User $actor): EquipmentTransfer
    {
        return DB::transaction(function () use ($actor, $data, $equipment): EquipmentTransfer {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);
            if (! $equipment->is_active || ! in_array($equipment->current_status, ['available', 'idle'], true)) {
                throw ValidationException::withMessages(['equipment' => 'Return the equipment to an available or idle state before requesting a transfer.']);
            }

            if ($equipment->assignments()->where('status', EquipmentAssignment::STATUS_ACTIVE)->exists()) {
                throw ValidationException::withMessages(['equipment' => 'Accept the active assignment return before requesting a transfer.']);
            }

            if ($equipment->transfers()->whereIn('status', [EquipmentTransfer::STATUS_REQUESTED, EquipmentTransfer::STATUS_APPROVED, EquipmentTransfer::STATUS_DISPATCHED])->exists()) {
                throw ValidationException::withMessages(['equipment' => 'This equipment already has an open transfer.']);
            }

            $destinationBranch = Branch::query()->where('status', 'active')->find($data['destination_branch_id']);
            if (! $destinationBranch instanceof Branch || ! in_array($destinationBranch->id, $this->branchContext->accessibleBranchIds($actor), true)) {
                throw ValidationException::withMessages(['destination_branch_id' => 'Select an active destination branch you can access.']);
            }

            $destinationLocation = EquipmentLocation::query()->where('branch_id', $destinationBranch->id)->where('is_active', true)->find($data['destination_location_id']);
            if (! $destinationLocation instanceof EquipmentLocation || $destinationLocation->id === $equipment->current_location_id) {
                throw ValidationException::withMessages(['destination_location_id' => 'Select a different active destination location.']);
            }

            $project = $destinationLocation->project_id === null ? null : Project::query()->find($destinationLocation->project_id);
            $site = $destinationLocation->site_id === null ? null : Site::query()->find($destinationLocation->site_id);
            if (isset($data['destination_project_id']) && $data['destination_project_id'] !== $destinationLocation->project_id) {
                throw ValidationException::withMessages(['destination_project_id' => 'The project must match the destination location.']);
            }

            if (isset($data['destination_site_id']) && $data['destination_site_id'] !== $destinationLocation->site_id) {
                throw ValidationException::withMessages(['destination_site_id' => 'The site must match the destination location.']);
            }

            $transfer = EquipmentTransfer::query()->create([
                'tenant_id' => $equipment->tenant_id,
                'equipment_id' => $equipment->id,
                'source_branch_id' => $equipment->branch_id,
                'source_location_id' => $equipment->current_location_id,
                'source_project_id' => $equipment->current_project_id,
                'source_site_id' => $equipment->current_site_id,
                'destination_branch_id' => $destinationBranch->id,
                'destination_location_id' => $destinationLocation->id,
                'destination_project_id' => $project?->id,
                'destination_site_id' => $site?->id,
                'reason' => $data['reason'],
                'status' => EquipmentTransfer::STATUS_REQUESTED,
                'requested_at' => now(),
                'requested_by' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->auditLogger->record('equipment.transfer.requested', $transfer, $actor, [], $transfer->toArray(), (string) $data['reason']);
            DB::afterCommit(fn () => $this->notificationService->requested($transfer));

            return $transfer->refresh();
        });
    }

    public function approve(EquipmentTransfer $transfer, User $actor): EquipmentTransfer
    {
        return DB::transaction(function () use ($actor, $transfer): EquipmentTransfer {
            $transfer = EquipmentTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status !== EquipmentTransfer::STATUS_REQUESTED) {
                throw ValidationException::withMessages(['transfer' => 'Only a requested transfer can be approved.']);
            }

            if ($transfer->requested_by === $actor->id) {
                throw ValidationException::withMessages(['transfer' => 'The transfer requester cannot approve their own request.']);
            }

            $transfer->forceFill(['status' => EquipmentTransfer::STATUS_APPROVED, 'approved_at' => now(), 'approved_by' => $actor->id, 'updated_by' => $actor->id])->save();
            $this->auditLogger->record('equipment.transfer.approved', $transfer, $actor, ['status' => EquipmentTransfer::STATUS_REQUESTED], $transfer->only(['status', 'approved_at', 'approved_by']));
            DB::afterCommit(fn () => $this->notificationService->approved($transfer));

            return $transfer->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function dispatch(EquipmentTransfer $transfer, array $data, User $actor): EquipmentTransfer
    {
        return DB::transaction(function () use ($actor, $data, $transfer): EquipmentTransfer {
            $transfer = EquipmentTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($transfer->equipment_id);
            if ($transfer->status !== EquipmentTransfer::STATUS_APPROVED || ! in_array($equipment->current_status, ['available', 'idle'], true)) {
                throw ValidationException::withMessages(['transfer' => 'Only an approved transfer for available equipment can be dispatched.']);
            }

            $dispatchedAt = CarbonImmutable::parse((string) $data['dispatched_at']);
            if ($dispatchedAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['dispatched_at' => 'Dispatch time cannot be in the future.']);
            }

            if ($equipment->meter_type !== 'none' && isset($data['dispatch_meter_reading'])) {
                $this->recordMeterReading->handle($equipment, ['reading_value' => $data['dispatch_meter_reading'], 'read_at' => $dispatchedAt, 'event_type' => 'transfer', 'evidence_note' => 'Transfer dispatch reading.'], $actor);
            }

            $transfer->forceFill([
                'status' => EquipmentTransfer::STATUS_DISPATCHED, 'dispatched_at' => $dispatchedAt,
                'dispatch_meter_reading' => $data['dispatch_meter_reading'] ?? null,
                'dispatch_condition' => $data['dispatch_condition'], 'transport_reference' => $data['transport_reference'] ?? null,
                'dispatched_by' => $actor->id, 'updated_by' => $actor->id,
            ])->save();
            $equipment->forceFill(['current_status' => 'transferred', 'current_custodian_id' => null, 'updated_by' => $actor->id])->save();
            $this->auditLogger->record('equipment.transfer.dispatched', $transfer, $actor, ['status' => EquipmentTransfer::STATUS_APPROVED], $transfer->only(['status', 'dispatched_at', 'dispatch_meter_reading', 'transport_reference']));
            DB::afterCommit(fn () => $this->notificationService->dispatched($transfer));

            return $transfer->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function receive(EquipmentTransfer $transfer, array $data, User $actor): EquipmentTransfer
    {
        return DB::transaction(function () use ($actor, $data, $transfer): EquipmentTransfer {
            $transfer = EquipmentTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($transfer->equipment_id);
            if ($transfer->status !== EquipmentTransfer::STATUS_DISPATCHED) {
                throw ValidationException::withMessages(['transfer' => 'Only dispatched equipment can be received.']);
            }

            if ($transfer->dispatched_by === $actor->id) {
                throw ValidationException::withMessages(['transfer' => 'The dispatching user cannot accept the destination receipt.']);
            }

            $receivedAt = CarbonImmutable::parse((string) $data['received_at']);
            if ($receivedAt->lessThan($transfer->dispatched_at) || $receivedAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['received_at' => 'Receipt time must follow dispatch and cannot be in the future.']);
            }

            if ($equipment->meter_type !== 'none' && isset($data['receipt_meter_reading'])) {
                $this->recordMeterReading->handle($equipment, [
                    'branch_id' => $transfer->destination_branch_id, 'reading_value' => $data['receipt_meter_reading'],
                    'read_at' => $receivedAt, 'project_id' => $transfer->destination_project_id,
                    'site_id' => $transfer->destination_site_id, 'equipment_location_id' => $transfer->destination_location_id,
                    'event_type' => 'transfer', 'evidence_note' => 'Transfer receipt reading.',
                ], $actor);
            }

            $transfer->forceFill([
                'status' => EquipmentTransfer::STATUS_RECEIVED, 'received_at' => $receivedAt,
                'receipt_meter_reading' => $data['receipt_meter_reading'] ?? null,
                'receipt_condition' => $data['receipt_condition'], 'received_by' => $actor->id, 'updated_by' => $actor->id,
            ])->save();
            $equipment->forceFill([
                'branch_id' => $transfer->destination_branch_id, 'current_status' => 'available',
                'default_location_id' => $transfer->source_branch_id === $transfer->destination_branch_id
                    ? $equipment->default_location_id
                    : $transfer->destination_location_id,
                'current_location_id' => $transfer->destination_location_id,
                'current_project_id' => $transfer->destination_project_id, 'current_site_id' => $transfer->destination_site_id,
                'current_custodian_id' => null, 'condition_summary' => $data['receipt_condition'], 'updated_by' => $actor->id,
            ])->save();
            $this->auditLogger->record('equipment.transfer.received', $transfer, $actor, ['status' => EquipmentTransfer::STATUS_DISPATCHED], $transfer->only(['status', 'received_at', 'receipt_meter_reading']));
            DB::afterCommit(fn () => $this->notificationService->received($transfer));

            return $transfer->refresh();
        });
    }
}
