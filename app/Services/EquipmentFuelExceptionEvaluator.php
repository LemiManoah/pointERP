<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailySiteReportEquipmentLine;
use App\Models\Equipment;

final class EquipmentFuelExceptionEvaluator
{
    /** @return array{status: string, reason: string, actual_rate: string|null, evidence_basis: string, meter_usage: string|null} */
    public function evaluate(Equipment $equipment, DailySiteReportEquipmentLine $line): array
    {
        $fuel = is_numeric($line->fuel_quantity) ? (float) $line->fuel_quantity : null;
        $expected = is_numeric($equipment->expected_fuel_efficiency) ? (float) $equipment->expected_fuel_efficiency : null;
        $opening = is_numeric($line->opening_meter_reading) ? (float) $line->opening_meter_reading : null;
        $closing = is_numeric($line->closing_meter_reading) ? (float) $line->closing_meter_reading : null;
        $workingHours = is_numeric($line->working_hours) ? (float) $line->working_hours : null;
        $meterUsage = $opening !== null && $closing !== null && $closing >= $opening ? $closing - $opening : null;

        if ($fuel === null || $fuel <= 0 || $expected === null || $expected <= 0) {
            return $this->insufficient('Fuel quantity or the equipment efficiency baseline is missing.', $meterUsage);
        }

        $basis = $equipment->fuel_efficiency_basis;
        if (! in_array($basis, ['litres_per_hour', 'litres_per_100km'], true)) {
            return $this->insufficient('The configured efficiency basis is not comparable with DSR evidence.', $meterUsage);
        }

        $denominator = $meterUsage;
        $evidenceBasis = 'accepted meter interval';

        if ($denominator === null || $denominator <= 0) {
            if ($basis === 'litres_per_hour' && $workingHours !== null && $workingHours > 0) {
                $denominator = $workingHours;
                $evidenceBasis = 'reported working hours';
            } else {
                return $this->insufficient('Comparable opening and closing meter evidence is missing.', $meterUsage);
            }
        }

        $actual = $basis === 'litres_per_100km'
            ? ($fuel / $denominator) * 100
            : $fuel / $denominator;
        $tolerance = is_numeric($equipment->fuel_tolerance_percent)
            ? (float) $equipment->fuel_tolerance_percent
            : 15.0;
        $variancePercent = abs($actual - $expected) / $expected * 100;
        $status = $variancePercent > $tolerance ? 'review_required' : 'within_tolerance';
        $reason = sprintf(
            'Actual %s, baseline %s, variance %s%% using %s.',
            number_format($actual, 4, '.', ''),
            number_format($expected, 4, '.', ''),
            number_format($variancePercent, 2, '.', ''),
            $evidenceBasis,
        );

        return [
            'status' => $status,
            'reason' => $reason,
            'actual_rate' => number_format($actual, 4, '.', ''),
            'evidence_basis' => $evidenceBasis,
            'meter_usage' => $meterUsage === null ? null : number_format($meterUsage, 4, '.', ''),
        ];
    }

    /** @return array{status: string, reason: string, actual_rate: null, evidence_basis: string, meter_usage: string|null} */
    private function insufficient(string $reason, ?float $meterUsage): array
    {
        return [
            'status' => 'insufficient_evidence',
            'reason' => $reason,
            'actual_rate' => null,
            'evidence_basis' => 'review only',
            'meter_usage' => $meterUsage === null ? null : number_format($meterUsage, 4, '.', ''),
        ];
    }
}
