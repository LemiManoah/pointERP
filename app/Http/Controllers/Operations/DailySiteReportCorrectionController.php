<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\CreateDailySiteReportCorrection;
use App\Http\Requests\Operations\DailySiteReports\StoreDailySiteReportCorrectionRequest;
use App\Models\DailySiteReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class DailySiteReportCorrectionController
{
    public function store(StoreDailySiteReportCorrectionRequest $request, DailySiteReport $dailySiteReport, CreateDailySiteReportCorrection $action): RedirectResponse
    {
        Gate::authorize('correct', $dailySiteReport);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array<string, mixed> $changes */
        $changes = $request->validated('changes');
        $rawEquipmentAdjustments = $changes['equipment_adjustments'] ?? [];

        if (! is_array($rawEquipmentAdjustments)) {
            throw ValidationException::withMessages([
                'changes.equipment_adjustments' => 'Invalid equipment adjustment payload.',
            ]);
        }

        /** @var list<array<string, mixed>> $equipmentAdjustments */
        $equipmentAdjustments = [];

        foreach ($rawEquipmentAdjustments as $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'changes.equipment_adjustments' => 'Invalid equipment adjustment payload.',
                ]);
            }

            $hasAdjustment = (float) ($item['working_hours_delta'] ?? 0) !== 0.0
                || (float) ($item['idle_hours_delta'] ?? 0) !== 0.0
                || (float) ($item['fuel_quantity_delta'] ?? 0) !== 0.0;

            if (! $hasAdjustment) {
                continue;
            }

            $line = $dailySiteReport->equipmentLines()->find((string) ($item['line_id'] ?? ''));

            if ($line === null) {
                throw ValidationException::withMessages([
                    'changes.equipment_adjustments' => 'One or more equipment lines do not belong to this report.',
                ]);
            }

            $equipmentAdjustments[] = [
                ...$item,
                'equipment_name' => $line->equipment_identifier ?? $line->equipment_name,
            ];
        }
        unset($changes['equipment_adjustments']);

        /** @var array<string, mixed> $newValues */
        $newValues = [];

        foreach ($changes as $field => $value) {
            if ((string) $dailySiteReport->getAttribute($field) !== (string) $value) {
                $newValues[$field] = $value;
            }
        }

        if ($equipmentAdjustments !== []) {
            $newValues['equipment_adjustments'] = $equipmentAdjustments;
        }

        if ($newValues === []) {
            throw ValidationException::withMessages([
                'changes' => 'Enter at least one proposed correction value.',
            ]);
        }

        $action->handle(
            report: $dailySiteReport,
            actor: $actor,
            reason: (string) $request->validated('reason'),
            newValues: $newValues,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Correction request recorded.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
