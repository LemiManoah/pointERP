<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\RequestEquipmentMeterCorrection;
use App\Http\Requests\Operations\EquipmentMeterReadings\StoreEquipmentMeterCorrectionRequest;
use App\Models\EquipmentMeterReading;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMeterCorrectionController
{
    public function store(StoreEquipmentMeterCorrectionRequest $request, EquipmentMeterReading $equipmentMeterReading, RequestEquipmentMeterCorrection $action): RedirectResponse
    {
        Gate::authorize('correct', $equipmentMeterReading);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $correction = $action->handle($equipmentMeterReading, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meter correction submitted for approval.']);

        return to_route('equipment.show', ['equipment' => $correction->equipment_id, 'tab' => 'readings']);
    }
}
