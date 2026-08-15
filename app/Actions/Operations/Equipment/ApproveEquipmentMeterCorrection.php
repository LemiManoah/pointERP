<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\EquipmentMeterReading;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EquipmentMeterNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ApproveEquipmentMeterCorrection
{
    public function __construct(
        private RecalculateEquipmentMeterState $recalculate,
        private AuditLogger $auditLogger,
        private EquipmentMeterNotificationService $notifications,
    ) {}

    public function handle(EquipmentMeterReading $correction, User $actor, ?string $decisionNote = null): EquipmentMeterReading
    {
        $correction = DB::transaction(function () use ($actor, $correction, $decisionNote): EquipmentMeterReading {
            $correction = EquipmentMeterReading::query()->with(['equipment.branch', 'correctedReading'])->lockForUpdate()->findOrFail($correction->id);
            if ($correction->event_type !== 'correction' || $correction->status !== EquipmentMeterReading::STATUS_PENDING) {
                throw ValidationException::withMessages(['correction' => 'Only a pending correction can be approved.']);
            }

            if ($correction->recorded_by === $actor->id && ! $actor->can('equipment.readings.override-self-approval')) {
                throw ValidationException::withMessages(['correction' => 'You cannot approve your own meter correction.']);
            }

            $target = $correction->correctedReading;
            if (! $target instanceof EquipmentMeterReading || $target->status !== EquipmentMeterReading::STATUS_ACCEPTED) {
                throw ValidationException::withMessages(['correction' => 'The source reading is no longer available for correction.']);
            }

            $base = EquipmentMeterReading::query()
                ->where('equipment_id', $correction->equipment_id)
                ->where('status', EquipmentMeterReading::STATUS_ACCEPTED)
                ->whereKeyNot($target->id);
            $previous = (clone $base)
                ->where(fn (Builder $query): Builder => $query->where('read_at', '<', $target->read_at)->orWhere(fn (Builder $query): Builder => $query->where('read_at', $target->read_at)->where('id', '<', $target->id)))
                ->latest('read_at')->latest('id')->first();
            $next = (clone $base)
                ->where(fn (Builder $query): Builder => $query->where('read_at', '>', $target->read_at)->orWhere(fn (Builder $query): Builder => $query->where('read_at', $target->read_at)->where('id', '>', $target->id)))
                ->oldest('read_at')->oldest('id')->first();
            $value = (float) $correction->reading_value;

            if ($previous instanceof EquipmentMeterReading && $value < (float) $previous->reading_value) {
                throw ValidationException::withMessages(['reading_value' => 'The corrected value is below the previous accepted reading.']);
            }

            if ($next instanceof EquipmentMeterReading && $value > (float) $next->reading_value) {
                throw ValidationException::withMessages(['reading_value' => 'The corrected value is above the next accepted reading.']);
            }

            $target->update(['status' => EquipmentMeterReading::STATUS_SUPERSEDED, 'updated_by' => $actor->id]);
            $correction->update([
                'status' => EquipmentMeterReading::STATUS_ACCEPTED,
                'decision_note' => $decisionNote,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'updated_by' => $actor->id,
            ]);
            $this->recalculate->handle($correction->equipment);
            $this->auditLogger->record('equipment.meter_correction.approved', $correction, $actor, ['source_status' => EquipmentMeterReading::STATUS_ACCEPTED, 'reading_value' => $target->reading_value], ['source_status' => EquipmentMeterReading::STATUS_SUPERSEDED, 'reading_value' => $correction->reading_value], $correction->reason, $correction->equipment->branch);

            return $correction->refresh();
        });
        $this->notifications->correctionReviewed($correction, true);

        return $correction;
    }
}
