<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordEquipmentMeterReading
{
    public function __construct(private RecalculateEquipmentMeterState $recalculate, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(Equipment $equipment, array $data, User $actor): EquipmentMeterReading
    {
        return DB::transaction(function () use ($actor, $data, $equipment): EquipmentMeterReading {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);
            if (! $equipment->is_active || $equipment->current_status === 'retired') {
                throw ValidationException::withMessages(['equipment' => 'Retired or inactive equipment cannot receive readings.']);
            }

            if ($equipment->meter_type === 'none') {
                throw ValidationException::withMessages(['reading_value' => 'This asset is configured without a meter.']);
            }

            $readAt = CarbonImmutable::parse((string) $data['read_at']);
            if ($readAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['read_at' => 'The reading time cannot be in the future.']);
            }

            $latest = EquipmentMeterReading::query()
                ->where('equipment_id', $equipment->id)
                ->where('status', EquipmentMeterReading::STATUS_ACCEPTED)
                ->latest('read_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $value = (float) $data['reading_value'];

            if ($latest instanceof EquipmentMeterReading && $readAt->lessThan($latest->read_at)) {
                throw ValidationException::withMessages(['read_at' => 'A normal reading cannot be earlier than the latest accepted reading.']);
            }

            if ($latest instanceof EquipmentMeterReading && $value < (float) $latest->reading_value) {
                throw ValidationException::withMessages(['reading_value' => 'The reading is below the latest accepted value. Submit a correction instead.']);
            }

            $reading = EquipmentMeterReading::query()->create([
                'tenant_id' => $equipment->tenant_id,
                'branch_id' => $equipment->branch_id,
                'equipment_id' => $equipment->id,
                'project_id' => $data['project_id'] ?? $equipment->current_project_id,
                'site_id' => $data['site_id'] ?? $equipment->current_site_id,
                'equipment_location_id' => $data['equipment_location_id'] ?? $equipment->current_location_id,
                'event_type' => $data['event_type'] ?? 'manual',
                'reading_value' => $data['reading_value'],
                'read_at' => $readAt,
                'status' => EquipmentMeterReading::STATUS_ACCEPTED,
                'evidence_note' => $data['evidence_note'] ?? null,
                'recorded_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->recalculate->handle($equipment);
            $this->auditLogger->record('equipment.meter_reading.accepted', $reading, $actor, [], $reading->fresh()?->toArray() ?? [], branch: $equipment->branch);

            return $reading->refresh();
        });
    }
}
