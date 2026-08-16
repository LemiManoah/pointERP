<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;

final readonly class EquipmentMaintenanceNotificationService
{
    public function __construct(private OperationalNotificationSender $notifications) {}

    public function created(EquipmentMaintenanceWorkOrder $workOrder): void
    {
        $recipients = User::query()->where('tenant_id', $workOrder->tenant_id)->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->id !== $workOrder->requested_by
                && $user->can('equipment.maintenance.approve')
                && ($user->can('branches.view-all') || $user->branches()->whereKey($workOrder->branch_id)->exists()))
            ->values();
        $this->notifications->send($recipients, $this->payload($workOrder, 'info', 'Maintenance work order awaiting approval'));
    }

    public function changed(EquipmentMaintenanceWorkOrder $workOrder, string $severity, string $title): void
    {
        $recipients = User::query()->whereIn('id', array_filter([$workOrder->requested_by, $workOrder->approved_by]))
            ->where('is_active', true)->get();
        $this->notifications->send($recipients, $this->payload($workOrder, $severity, $title));
    }

    /** @return array<string, mixed> */
    private function payload(EquipmentMaintenanceWorkOrder $workOrder, string $severity, string $title): array
    {
        $workOrder->loadMissing('equipment');

        return [
            'tenant_id' => $workOrder->tenant_id, 'branch_id' => $workOrder->branch_id,
            'equipment_id' => $workOrder->equipment_id, 'equipment_maintenance_work_order_id' => $workOrder->id,
            'category' => 'equipment_maintenance', 'severity' => $severity, 'title' => $title,
            'message' => sprintf('%s maintenance order %s is %s.', $workOrder->equipment->asset_code, $workOrder->reference, $workOrder->status),
            'action_url' => '/equipment/'.$workOrder->equipment_id.'?tab=maintenance',
        ];
    }
}
