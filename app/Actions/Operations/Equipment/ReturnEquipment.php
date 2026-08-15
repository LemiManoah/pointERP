<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EquipmentAssignmentNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReturnEquipment
{
    public function __construct(
        private RecordEquipmentMeterReading $recordMeterReading,
        private AuditLogger $auditLogger,
        private EquipmentAssignmentNotificationService $notificationService,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(EquipmentAssignment $assignment, array $data, User $actor): EquipmentAssignment
    {
        return DB::transaction(function () use ($actor, $assignment, $data): EquipmentAssignment {
            $assignment = EquipmentAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($assignment->equipment_id);

            if ($assignment->status !== EquipmentAssignment::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['assignment' => 'Only an active assignment can be returned.']);
            }

            $returnedAt = CarbonImmutable::parse((string) $data['returned_at']);
            if ($returnedAt->lessThan($assignment->assigned_at) || $returnedAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['returned_at' => 'Return time must be after handover and cannot be in the future.']);
            }

            $returnLocation = EquipmentLocation::query()
                ->where('branch_id', $assignment->branch_id)
                ->where('is_active', true)
                ->find($data['return_location_id']);

            if (! $returnLocation instanceof EquipmentLocation) {
                throw ValidationException::withMessages(['return_location_id' => 'Select an active return location in the equipment branch.']);
            }

            if ($equipment->meter_type !== 'none' && isset($data['return_meter_reading'])) {
                $this->recordMeterReading->handle($equipment, [
                    'reading_value' => $data['return_meter_reading'],
                    'read_at' => $returnedAt,
                    'project_id' => $assignment->project_id,
                    'site_id' => $assignment->site_id,
                    'equipment_location_id' => $returnLocation->id,
                    'event_type' => 'return',
                    'evidence_note' => 'Assignment return reading.',
                ], $actor);
            }

            $oldValues = $assignment->toArray();
            $assignment->forceFill([
                'returned_at' => $returnedAt,
                'return_meter_reading' => $data['return_meter_reading'] ?? null,
                'return_condition' => $data['return_condition'],
                'return_notes' => $data['return_notes'] ?? null,
                'return_location_id' => $returnLocation->id,
                'status' => EquipmentAssignment::STATUS_RETURNED,
                'returned_by' => $actor->id,
                'accepted_return_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $equipment->forceFill([
                'current_status' => 'available',
                'current_location_id' => $returnLocation->id,
                'current_project_id' => null,
                'current_site_id' => null,
                'current_custodian_id' => null,
                'condition_summary' => $data['return_condition'],
                'updated_by' => $actor->id,
            ])->save();

            $returnNotes = isset($data['return_notes']) ? (string) $data['return_notes'] : null;
            $this->auditLogger->record('equipment.assignment.returned', $assignment, $actor, $oldValues, $assignment->toArray(), $returnNotes, $equipment->branch);
            DB::afterCommit(fn () => $this->notificationService->returned($assignment));

            return $assignment->refresh();
        });
    }
}
