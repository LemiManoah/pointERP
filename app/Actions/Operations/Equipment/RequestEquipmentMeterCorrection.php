<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\EquipmentMeterReading;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EquipmentMeterNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RequestEquipmentMeterCorrection
{
    public function __construct(private AuditLogger $auditLogger, private EquipmentMeterNotificationService $notifications) {}

    /** @param array<string, mixed> $data */
    public function handle(EquipmentMeterReading $target, array $data, User $actor): EquipmentMeterReading
    {
        $correction = DB::transaction(function () use ($actor, $data, $target): EquipmentMeterReading {
            $target = EquipmentMeterReading::query()->with('equipment')->lockForUpdate()->findOrFail($target->id);
            if ($target->status !== EquipmentMeterReading::STATUS_ACCEPTED || $target->event_type === 'correction') {
                throw ValidationException::withMessages(['reading' => 'Only an accepted source reading can be corrected.']);
            }

            if ($target->corrections()->where('status', EquipmentMeterReading::STATUS_PENDING)->exists()) {
                throw ValidationException::withMessages(['reading' => 'This reading already has a pending correction.']);
            }

            $correction = EquipmentMeterReading::query()->create([
                'tenant_id' => $target->tenant_id,
                'branch_id' => $target->branch_id,
                'equipment_id' => $target->equipment_id,
                'project_id' => $target->project_id,
                'site_id' => $target->site_id,
                'equipment_location_id' => $target->equipment_location_id,
                'event_type' => 'correction',
                'reading_value' => $data['reading_value'],
                'read_at' => $target->read_at,
                'status' => EquipmentMeterReading::STATUS_PENDING,
                'corrects_reading_id' => $target->id,
                'reason' => $data['reason'],
                'evidence_note' => $data['evidence_note'] ?? null,
                'recorded_by' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->auditLogger->record('equipment.meter_correction.requested', $correction, $actor, [], $correction->fresh()?->toArray() ?? [], (string) $data['reason'], $target->equipment->branch);

            return $correction;
        });
        $this->notifications->correctionRequested($correction, $actor);

        return $correction;
    }
}
