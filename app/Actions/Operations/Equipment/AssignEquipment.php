<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentLocation;
use App\Models\EquipmentTransfer;
use App\Models\Project;
use App\Models\Site;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EquipmentAssignmentNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class AssignEquipment
{
    public function __construct(
        private RecordEquipmentMeterReading $recordMeterReading,
        private AuditLogger $auditLogger,
        private EquipmentAssignmentNotificationService $notificationService,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Equipment $equipment, array $data, User $actor): EquipmentAssignment
    {
        return DB::transaction(function () use ($actor, $data, $equipment): EquipmentAssignment {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);

            if (! $equipment->is_active || ! in_array($equipment->current_status, ['available', 'idle'], true)) {
                throw ValidationException::withMessages(['equipment' => 'Only active, available or idle equipment can be assigned.']);
            }

            if ($equipment->assignments()->where('status', EquipmentAssignment::STATUS_ACTIVE)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['equipment' => 'This equipment already has an active assignment.']);
            }

            if ($equipment->transfers()->whereIn('status', [EquipmentTransfer::STATUS_REQUESTED, EquipmentTransfer::STATUS_APPROVED, EquipmentTransfer::STATUS_DISPATCHED])->exists()) {
                throw ValidationException::withMessages(['equipment' => 'Complete or cancel the open transfer before assigning this equipment.']);
            }

            $project = Project::query()->where('branch_id', $equipment->branch_id)->find($data['project_id']);
            $site = Site::query()->where('branch_id', $equipment->branch_id)->where('project_id', $project?->id)->find($data['site_id']);
            $location = EquipmentLocation::query()->where('branch_id', $equipment->branch_id)->where('is_active', true)->find($data['equipment_location_id']);

            if (! $project instanceof Project || ! Gate::forUser($actor)->allows('view', $project)) {
                throw ValidationException::withMessages(['project_id' => 'Select a project you are authorised to access.']);
            }

            if (! $site instanceof Site || ! Gate::forUser($actor)->allows('view', $site)) {
                throw ValidationException::withMessages(['site_id' => 'Select a site belonging to that project.']);
            }

            if (! $location instanceof EquipmentLocation || ($location->project_id !== null && $location->project_id !== $project->id) || ($location->site_id !== null && $location->site_id !== $site->id)) {
                throw ValidationException::withMessages(['equipment_location_id' => 'Select a compatible active location in the equipment branch.']);
            }

            $staff = isset($data['custodian_staff_id'])
                ? Staff::query()->where('branch_id', $equipment->branch_id)->where('status', 'active')->find($data['custodian_staff_id'])
                : null;
            $externalName = mb_trim((string) ($data['external_custodian_name'] ?? ''));

            if (! $staff instanceof Staff && $externalName === '') {
                throw ValidationException::withMessages(['custodian_staff_id' => 'Select an internal custodian or enter an external custodian.']);
            }

            $assignedAt = CarbonImmutable::parse((string) $data['assigned_at']);
            if ($assignedAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['assigned_at' => 'The handover time cannot be in the future.']);
            }

            $expectedReturnAt = isset($data['expected_return_at']) ? CarbonImmutable::parse((string) $data['expected_return_at']) : null;
            if ($expectedReturnAt instanceof CarbonImmutable && $expectedReturnAt->lessThanOrEqualTo($assignedAt)) {
                throw ValidationException::withMessages(['expected_return_at' => 'The expected return must be after the handover time.']);
            }

            $assignment = EquipmentAssignment::query()->create([
                'tenant_id' => $equipment->tenant_id,
                'equipment_id' => $equipment->id,
                'branch_id' => $equipment->branch_id,
                'project_id' => $project->id,
                'site_id' => $site->id,
                'equipment_location_id' => $location->id,
                'custodian_staff_id' => $staff?->id,
                'external_custodian_name' => $externalName !== '' ? $externalName : null,
                'external_custodian_employer' => $data['external_custodian_employer'] ?? null,
                'assigned_at' => $assignedAt,
                'expected_return_at' => $expectedReturnAt,
                'handover_meter_reading' => $data['handover_meter_reading'] ?? null,
                'handover_condition' => $data['handover_condition'],
                'assignment_notes' => $data['assignment_notes'] ?? null,
                'status' => EquipmentAssignment::STATUS_ACTIVE,
                'handed_over_by' => $actor->id,
                'received_by' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($equipment->meter_type !== 'none' && isset($data['handover_meter_reading'])) {
                $this->recordMeterReading->handle($equipment, [
                    'reading_value' => $data['handover_meter_reading'],
                    'read_at' => $assignedAt,
                    'project_id' => $project->id,
                    'site_id' => $site->id,
                    'equipment_location_id' => $location->id,
                    'event_type' => 'assignment',
                    'evidence_note' => 'Assignment handover reading.',
                ], $actor);
            }

            $equipment->forceFill([
                'current_status' => 'assigned',
                'current_location_id' => $location->id,
                'current_project_id' => $project->id,
                'current_site_id' => $site->id,
                'current_custodian_id' => $staff?->id,
                'condition_summary' => $data['handover_condition'],
                'updated_by' => $actor->id,
            ])->save();

            $this->auditLogger->record('equipment.assignment.activated', $assignment, $actor, [], $assignment->toArray(), branch: $equipment->branch);
            DB::afterCommit(fn () => $this->notificationService->assigned($assignment));

            return $assignment->refresh();
        });
    }
}
