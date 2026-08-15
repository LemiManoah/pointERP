<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentFuelTransaction;
use App\Http\Requests\Operations\EquipmentFuelTransactions\ReverseEquipmentFuelTransactionRequest;
use App\Models\EquipmentFuelTransaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class EquipmentFuelTransactionReversalController
{
    public function __invoke(ReverseEquipmentFuelTransactionRequest $request, EquipmentFuelTransaction $equipmentFuelTransaction, ManageEquipmentFuelTransaction $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->reverse($equipmentFuelTransaction, (string) $request->validated('reason'), $actor);
        Inertia::flash('toast', ['type' => 'warning', 'message' => 'Fuel transaction reversed with an additive ledger entry.']);

        return back();
    }
}
