<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\RejectEquipmentMeterCorrection;
use App\Http\Requests\Operations\EquipmentMeterReadings\ReviewEquipmentMeterCorrectionRequest;
use App\Models\EquipmentMeterReading;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class EquipmentMeterCorrectionRejectionController
{
    public function __invoke(ReviewEquipmentMeterCorrectionRequest $request, EquipmentMeterReading $equipmentMeterReading, RejectEquipmentMeterCorrection $action): RedirectResponse
    {
        Gate::authorize('approveCorrection', $equipmentMeterReading);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $decisionNote = $request->string('decision_note')->trim()->value();
        if (mb_strlen($decisionNote) < 10) {
            throw ValidationException::withMessages(['decision_note' => 'A rejection note of at least 10 characters is required.']);
        }

        $action->handle($equipmentMeterReading, $actor, $decisionNote);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meter correction rejected.']);

        return to_route('equipment.show', ['equipment' => $equipmentMeterReading->equipment_id, 'tab' => 'readings']);
    }
}
