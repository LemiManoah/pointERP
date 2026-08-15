<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ConfirmEquipmentLocation;
use App\Http\Requests\Operations\EquipmentLocations\StoreEquipmentLocationConfirmationRequest;
use App\Models\Equipment;
use App\Models\EquipmentLocationConfirmation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentLocationConfirmationController
{
    public function store(StoreEquipmentLocationConfirmationRequest $request, Equipment $equipment, ConfirmEquipmentLocation $action): RedirectResponse
    {
        Gate::authorize('view', $equipment);
        Gate::authorize('create', EquipmentLocationConfirmation::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($equipment, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Last known equipment location confirmed.']);

        return to_route('equipment.show', ['equipment' => $equipment, 'tab' => 'locations']);
    }
}
