<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentTransfer;
use App\Http\Requests\Operations\EquipmentTransfers\StoreEquipmentTransferRequest;
use App\Models\Equipment;
use App\Models\EquipmentTransfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentTransferController
{
    public function store(StoreEquipmentTransferRequest $request, Equipment $equipment, ManageEquipmentTransfer $action): RedirectResponse
    {
        Gate::authorize('view', $equipment);
        Gate::authorize('create', EquipmentTransfer::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->request($equipment, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment transfer requested.']);

        return to_route('equipment.show', ['equipment' => $equipment, 'tab' => 'transfers']);
    }
}
