<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentFuelTransaction;
use App\Http\Requests\Operations\EquipmentFuelTransactions\ApproveEquipmentFuelTransactionRequest;
use App\Models\EquipmentFuelTransaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class EquipmentFuelTransactionApprovalController
{
    public function __invoke(ApproveEquipmentFuelTransactionRequest $request, EquipmentFuelTransaction $equipmentFuelTransaction, ManageEquipmentFuelTransaction $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->approve($equipmentFuelTransaction, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fuel transaction approved and posted.']);

        return back();
    }
}
