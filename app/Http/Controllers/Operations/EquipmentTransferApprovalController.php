<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ManageEquipmentTransfer;
use App\Models\EquipmentTransfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentTransferApprovalController
{
    public function __invoke(EquipmentTransfer $equipmentTransfer, ManageEquipmentTransfer $action): RedirectResponse
    {
        Gate::authorize('approve', $equipmentTransfer);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $action->approve($equipmentTransfer, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment transfer approved.']);

        return back();
    }
}
