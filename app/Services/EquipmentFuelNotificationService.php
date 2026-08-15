<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EquipmentFuelTransaction;
use App\Models\User;

final readonly class EquipmentFuelNotificationService
{
    public function __construct(private OperationalNotificationSender $notifications) {}

    public function submitted(EquipmentFuelTransaction $transaction): void
    {
        $recipients = User::query()->where('tenant_id', $transaction->tenant_id)->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->id !== $transaction->submitted_by
                && $user->can('equipment.fuel.approve')
                && ($user->can('branches.view-all') || $user->branches()->whereKey($transaction->branch_id)->exists()))
            ->values();
        $this->notifications->send($recipients, $this->payload($transaction, 'info', 'Fuel transaction awaiting approval'));
    }

    public function reviewed(EquipmentFuelTransaction $transaction, bool $reversed): void
    {
        $submitter = User::query()->whereKey($transaction->submitted_by)->where('is_active', true)->first();
        if ($submitter instanceof User) {
            $this->notifications->send(collect([$submitter]), $this->payload($transaction, $reversed ? 'warning' : 'success', $reversed ? 'Fuel transaction reversed' : 'Fuel transaction posted'));
        }
    }

    /** @return array<string, mixed> */
    private function payload(EquipmentFuelTransaction $transaction, string $severity, string $title): array
    {
        $transaction->loadMissing('equipment');

        return [
            'tenant_id' => $transaction->tenant_id, 'branch_id' => $transaction->branch_id,
            'equipment_id' => $transaction->equipment_id, 'equipment_fuel_transaction_id' => $transaction->id,
            'category' => 'equipment_fuel', 'severity' => $severity, 'title' => $title,
            'message' => sprintf('%s fuel transaction is %s.', $transaction->equipment->asset_code, $transaction->status),
            'action_url' => '/equipment/'.$transaction->equipment_id.'?tab=fuel',
        ];
    }
}
