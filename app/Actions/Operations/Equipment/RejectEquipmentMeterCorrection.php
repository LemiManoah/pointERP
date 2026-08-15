<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\EquipmentMeterReading;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EquipmentMeterNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RejectEquipmentMeterCorrection
{
    public function __construct(private AuditLogger $auditLogger, private EquipmentMeterNotificationService $notifications) {}

    public function handle(EquipmentMeterReading $correction, User $actor, string $decisionNote): EquipmentMeterReading
    {
        $correction = DB::transaction(function () use ($actor, $correction, $decisionNote): EquipmentMeterReading {
            $correction = EquipmentMeterReading::query()->with('equipment.branch')->lockForUpdate()->findOrFail($correction->id);
            if ($correction->event_type !== 'correction' || $correction->status !== EquipmentMeterReading::STATUS_PENDING) {
                throw ValidationException::withMessages(['correction' => 'Only a pending correction can be rejected.']);
            }

            $correction->update([
                'status' => EquipmentMeterReading::STATUS_REJECTED,
                'decision_note' => $decisionNote,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'updated_by' => $actor->id,
            ]);
            $this->auditLogger->record('equipment.meter_correction.rejected', $correction, $actor, ['status' => EquipmentMeterReading::STATUS_PENDING], ['status' => EquipmentMeterReading::STATUS_REJECTED], $decisionNote, $correction->equipment->branch);

            return $correction->refresh();
        });
        $this->notifications->correctionReviewed($correction, false);

        return $correction;
    }
}
