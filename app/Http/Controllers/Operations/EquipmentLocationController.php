<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\SaveEquipmentLocation;
use App\Http\Requests\Operations\EquipmentLocations\StoreEquipmentLocationRequest;
use App\Http\Requests\Operations\EquipmentLocations\UpdateEquipmentLocationRequest;
use App\Models\Equipment;
use App\Models\EquipmentLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class EquipmentLocationController
{
    public function store(StoreEquipmentLocationRequest $request, SaveEquipmentLocation $action): RedirectResponse
    {
        Gate::authorize('create', EquipmentLocation::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment location saved.']);

        return to_route('equipment.index', ['tab' => 'locations']);
    }

    public function update(UpdateEquipmentLocationRequest $request, EquipmentLocation $equipmentLocation, SaveEquipmentLocation $action): RedirectResponse
    {
        Gate::authorize('update', $equipmentLocation);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $equipmentLocation);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment location updated.']);

        return to_route('equipment.index', ['tab' => 'locations']);
    }

    public function destroy(EquipmentLocation $equipmentLocation, SaveEquipmentLocation $action): RedirectResponse
    {
        Gate::authorize('delete', $equipmentLocation);
        if ($equipmentLocation->is_active && Equipment::query()->where('is_active', true)->where(fn (Builder $query) => $query->where('default_location_id', $equipmentLocation->id)->orWhere('current_location_id', $equipmentLocation->id))->exists()) {
            throw ValidationException::withMessages(['location' => 'Move or retire active equipment before deactivating this location.']);
        }

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $data = $equipmentLocation->only(['branch_id', 'project_id', 'site_id', 'type', 'code', 'name', 'address', 'latitude', 'longitude']);
        $action->handle([...$data, 'is_active' => ! $equipmentLocation->is_active], $actor, $equipmentLocation);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment location status changed.']);

        return to_route('equipment.index', ['tab' => 'locations']);
    }
}
