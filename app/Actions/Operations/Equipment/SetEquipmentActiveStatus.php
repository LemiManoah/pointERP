<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Equipment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

final readonly class SetEquipmentActiveStatus
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Equipment $equipment, User $actor): Equipment
    {
        $oldValues = $equipment->only(['current_status', 'is_active']);
        $restoring = ! $equipment->is_active;

        if (! $restoring && $equipment->assignments()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages([
                'equipment' => 'This equipment is currently assigned. Accept its return before retiring it.',
            ]);
        }

        if (! $restoring && $equipment->transfers()->whereIn('status', ['requested', 'approved', 'dispatched'])->exists()) {
            throw ValidationException::withMessages([
                'equipment' => 'This equipment has an open transfer. Complete or cancel the transfer before retiring it.',
            ]);
        }

        if (! $restoring && in_array($equipment->current_status, ['transferred', 'under_maintenance'], true)) {
            throw ValidationException::withMessages([
                'equipment' => 'This equipment has an open transfer or maintenance workflow. Close it before retiring the asset.',
            ]);
        }

        $equipment->update([
            'current_status' => $restoring ? 'available' : 'retired',
            'is_active' => $restoring,
            'updated_by' => $actor->id,
        ]);
        $this->auditLogger->record(
            $restoring ? 'equipment.asset.restored' : 'equipment.asset.retired',
            $equipment,
            $actor,
            $oldValues,
            $equipment->only(['current_status', 'is_active']),
        );

        return $equipment;
    }
}
