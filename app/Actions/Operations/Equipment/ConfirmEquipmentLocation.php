<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentLocation;
use App\Models\EquipmentLocationConfirmation;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmEquipmentLocation
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(Equipment $equipment, array $data, User $actor): EquipmentLocationConfirmation
    {
        return DB::transaction(function () use ($actor, $data, $equipment): EquipmentLocationConfirmation {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);
            if (! $equipment->is_active || in_array($equipment->current_status, ['retired', 'transferred'], true)) {
                throw ValidationException::withMessages(['equipment' => 'Location cannot be manually confirmed while equipment is retired or in transit.']);
            }

            $location = EquipmentLocation::query()->where('branch_id', $equipment->branch_id)->where('is_active', true)->find($data['equipment_location_id']);
            if (! $location instanceof EquipmentLocation) {
                throw ValidationException::withMessages(['equipment_location_id' => 'Select an active location in the equipment branch.']);
            }

            $assignment = $equipment->assignments()->where('status', EquipmentAssignment::STATUS_ACTIVE)->first();
            if ($assignment instanceof EquipmentAssignment && $location->site_id !== null && $location->site_id !== $assignment->site_id) {
                throw ValidationException::withMessages(['equipment_location_id' => 'Return or transfer the assigned equipment before confirming it at another site.']);
            }

            $observedAt = CarbonImmutable::parse((string) $data['observed_at']);
            if ($observedAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['observed_at' => 'Observation time cannot be in the future.']);
            }

            $confirmation = EquipmentLocationConfirmation::query()->create([
                'tenant_id' => $equipment->tenant_id, 'equipment_id' => $equipment->id, 'branch_id' => $equipment->branch_id,
                'equipment_location_id' => $location->id, 'project_id' => $location->project_id, 'site_id' => $location->site_id,
                'observed_at' => $observedAt, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
                'observed_status' => $data['observed_status'] ?? null, 'condition_observation' => $data['condition_observation'] ?? null,
                'note' => $data['note'] ?? null, 'confirmed_by' => $actor->id, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $equipment->forceFill([
                'current_location_id' => $location->id, 'current_project_id' => $location->project_id,
                'current_site_id' => $location->site_id,
                'condition_summary' => $data['condition_observation'] ?? $equipment->condition_summary,
                'updated_by' => $actor->id,
            ])->save();
            $this->auditLogger->record('equipment.location.confirmed', $confirmation, $actor, [], $confirmation->toArray(), isset($data['note']) ? (string) $data['note'] : null);

            return $confirmation->refresh();
        });
    }
}
