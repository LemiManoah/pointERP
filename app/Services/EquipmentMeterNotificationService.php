<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EquipmentMeterReading;
use App\Models\User;

final readonly class EquipmentMeterNotificationService
{
    public function __construct(private OperationalNotificationSender $notifications) {}

    public function correctionRequested(EquipmentMeterReading $correction, User $requester): void
    {
        $correction->loadMissing('equipment');
        $recipients = User::query()
            ->where('tenant_id', $correction->tenant_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->id !== $requester->id
                && $user->can('equipment.readings.approve-correction')
                && ($user->can('equipment.view-all') || $user->can('branches.view-all') || $user->branches()->whereKey($correction->branch_id)->exists()));

        $this->notifications->send($recipients, [
            'tenant_id' => $correction->tenant_id,
            'branch_id' => $correction->branch_id,
            'equipment_id' => $correction->equipment_id,
            'meter_reading_id' => $correction->id,
            'category' => 'equipment_meter',
            'severity' => 'warning',
            'title' => 'Meter correction awaiting review',
            'message' => sprintf('%s has a meter correction awaiting approval.', $correction->equipment->asset_code),
            'action_url' => '/equipment/'.$correction->equipment_id,
        ]);
    }

    public function correctionReviewed(EquipmentMeterReading $correction, bool $approved): void
    {
        $correction->loadMissing(['equipment', 'recordedBy']);
        $recipient = $correction->recordedBy;
        if (! $recipient instanceof User) {
            return;
        }

        $this->notifications->send(collect([$recipient]), [
            'tenant_id' => $correction->tenant_id,
            'branch_id' => $correction->branch_id,
            'equipment_id' => $correction->equipment_id,
            'meter_reading_id' => $correction->id,
            'category' => 'equipment_meter',
            'severity' => $approved ? 'info' : 'warning',
            'title' => $approved ? 'Meter correction approved' : 'Meter correction rejected',
            'message' => sprintf('%s meter correction was %s.', $correction->equipment->asset_code, $approved ? 'approved' : 'rejected'),
            'action_url' => '/equipment/'.$correction->equipment_id,
        ]);
    }
}
