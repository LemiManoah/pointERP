<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EquipmentTransfer;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class EquipmentTransferNotificationService
{
    public function __construct(private OperationalNotificationSender $notifications) {}

    public function requested(EquipmentTransfer $transfer): void
    {
        $recipients = $this->authorisedUsers($transfer, 'equipment.transfers.approve', $transfer->source_branch_id)
            ->reject(fn (User $user): bool => $user->id === $transfer->requested_by);
        $this->notifications->send($recipients, $this->payload($transfer, 'info', 'Equipment transfer awaiting approval'));
    }

    public function approved(EquipmentTransfer $transfer): void
    {
        $this->notifyUsers($transfer, [$transfer->requested_by], 'success', 'Equipment transfer approved');
    }

    public function dispatched(EquipmentTransfer $transfer): void
    {
        $recipients = $this->authorisedUsers($transfer, 'equipment.transfers.receive', $transfer->destination_branch_id);
        $this->notifications->send($recipients, $this->payload($transfer, 'warning', 'Equipment dispatched to destination'));
    }

    public function received(EquipmentTransfer $transfer): void
    {
        $this->notifyUsers($transfer, [$transfer->requested_by, $transfer->approved_by], 'success', 'Equipment received at destination');
    }

    /** @return Collection<int, User> */
    private function authorisedUsers(EquipmentTransfer $transfer, string $permission, string $branchId): Collection
    {
        return User::query()
            ->where('tenant_id', $transfer->tenant_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->can($permission)
                && ($user->can('branches.view-all') || $user->branches()->whereKey($branchId)->exists()))
            ->values();
    }

    /** @param list<string|null> $userIds */
    private function notifyUsers(EquipmentTransfer $transfer, array $userIds, string $severity, string $title): void
    {
        $recipients = User::query()->whereIn('id', array_filter($userIds))->where('is_active', true)->get();
        $this->notifications->send($recipients, $this->payload($transfer, $severity, $title));
    }

    /** @return array<string, mixed> */
    private function payload(EquipmentTransfer $transfer, string $severity, string $title): array
    {
        $transfer->loadMissing(['equipment', 'destinationLocation']);

        return [
            'tenant_id' => $transfer->tenant_id,
            'branch_id' => $transfer->destination_branch_id,
            'equipment_id' => $transfer->equipment_id,
            'equipment_transfer_id' => $transfer->id,
            'category' => 'equipment_transfer',
            'severity' => $severity,
            'title' => $title,
            'message' => sprintf('%s transfer to %s is %s.', $transfer->equipment->asset_code, $transfer->destinationLocation->name, $transfer->status),
            'action_url' => '/equipment/'.$transfer->equipment_id.'?tab=transfers',
        ];
    }
}
