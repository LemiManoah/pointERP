<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\ApproveEquipmentMeterCorrection;
use App\Http\Requests\Operations\EquipmentMeterReadings\ReviewEquipmentMeterCorrectionRequest;
use App\Models\EquipmentMeterReading;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class EquipmentMeterCorrectionApprovalController
{
    public function __invoke(ReviewEquipmentMeterCorrectionRequest $request, EquipmentMeterReading $equipmentMeterReading, ApproveEquipmentMeterCorrection $action): RedirectResponse
    {
        Gate::authorize('approveCorrection', $equipmentMeterReading);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($equipmentMeterReading, $actor, $request->string('decision_note')->value() ?: null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meter correction approved.']);

        return to_route('equipment.show', ['equipment' => $equipmentMeterReading->equipment_id, 'tab' => 'readings']);
    }
}
