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

        $workAdjustments = [];
        foreach (($changes['work_adjustments'] ?? []) as $item) {
            if ((float) ($item['quantity_delta'] ?? 0) === 0.0) {
                continue;
            }
            $line = $dailySiteReport->workLines()->where('counts_towards_boq', true)->find($item['line_id']);
            if (! $line) {
                throw ValidationException::withMessages(['changes.work_adjustments' => 'Select measured work from this report.']);
            }
            $workAdjustments[] = [...$item, 'description' => $line->description, 'unit' => $line->unit];
        }
        $usageTreatments = [];
        foreach (($changes['usage_treatments'] ?? []) as $item) {
            $relation = $item['group'].'Lines';
            $line = $dailySiteReport->{$relation}()->find($item['line_id']);
            if (! $line) {
                throw ValidationException::withMessages(['changes.usage_treatments' => 'Select resource usage from this report.']);
            }
            if ($line->getAttribute('work_type') !== $item['work_type']) {
                $usageTreatments[] = [...$item, 'previous_type' => $line->getAttribute('work_type')];
            }
        }
        unset($changes['equipment_adjustments'], $changes['work_adjustments'], $changes['usage_treatments']);

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
        if ($workAdjustments !== []) {
            $newValues['work_adjustments'] = $workAdjustments;
        }
        if ($usageTreatments !== []) {
            $newValues['usage_treatments'] = $usageTreatments;
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
