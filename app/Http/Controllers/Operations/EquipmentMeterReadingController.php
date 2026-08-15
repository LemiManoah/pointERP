<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\RecordEquipmentMeterReading;
use App\Http\Requests\Operations\EquipmentMeterReadings\StoreEquipmentMeterReadingRequest;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMeterReadingController
{
    public function store(StoreEquipmentMeterReadingRequest $request, Equipment $equipment, RecordEquipmentMeterReading $action): RedirectResponse
    {
        Gate::authorize('create', [EquipmentMeterReading::class, $equipment]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($equipment, [...$request->validated(), 'event_type' => 'manual'], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meter reading recorded.']);

        return to_route('equipment.show', ['equipment' => $equipment, 'tab' => 'readings']);
    }
}
