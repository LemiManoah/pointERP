<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentTransfer;
use App\Http\Requests\Operations\EquipmentTransfers\ReceiveEquipmentTransferRequest;
use App\Models\EquipmentTransfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentTransferReceiptController
{
    public function __invoke(ReceiveEquipmentTransferRequest $request, EquipmentTransfer $equipmentTransfer, ManageEquipmentTransfer $action): RedirectResponse
    {
        Gate::authorize('receive', $equipmentTransfer);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->receive($equipmentTransfer, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment received at its destination.']);

        return back();
    }
}
