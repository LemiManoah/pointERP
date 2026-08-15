<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\Equipment;
use App\Models\User;
use App\Services\AuditLogger;

final readonly class SetEquipmentActiveStatus
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Equipment $equipment, User $actor): Equipment
    {
        $oldValues = $equipment->only(['current_status', 'is_active']);
        $restoring = ! $equipment->is_active;
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
