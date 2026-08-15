<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentFuelTransaction;
use App\Http\Requests\Operations\EquipmentFuelTransactions\StoreEquipmentFuelTransactionRequest;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class EquipmentFuelTransactionController
{
    public function store(StoreEquipmentFuelTransactionRequest $request, Equipment $equipment, ManageEquipmentFuelTransaction $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->submit($equipment, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fuel transaction submitted for approval.']);

        return to_route('equipment.show', ['equipment' => $equipment, 'tab' => 'fuel']);
    }
}
