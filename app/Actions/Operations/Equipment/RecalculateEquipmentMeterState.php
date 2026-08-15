<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentMeterReading;

final class RecalculateEquipmentMeterState
{
    public function handle(Equipment $equipment): void
    {
        $readings = EquipmentMeterReading::query()
            ->where('equipment_id', $equipment->id)
            ->where('status', EquipmentMeterReading::STATUS_ACCEPTED)
            ->oldest('read_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $previous = null;
        foreach ($readings as $reading) {
            $usage = $previous === null ? null : number_format((float) $reading->reading_value - (float) $previous, 4, '.', '');
            $reading->forceFill(['previous_reading' => $previous, 'usage' => $usage])->save();
            $previous = $reading->reading_value;
        }

        $latest = $readings->last();
        $equipment->forceFill([
            'current_meter_reading' => $latest instanceof EquipmentMeterReading ? $latest->reading_value : null,
            'current_meter_read_at' => $latest instanceof EquipmentMeterReading ? $latest->read_at : null,
        ])->save();
    }
}
