<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EquipmentAssignment;
use App\Models\User;

final readonly class EquipmentAssignmentNotificationService
{
    public function __construct(private OperationalNotificationSender $notifications) {}

    public function assigned(EquipmentAssignment $assignment): void
    {
        $assignment->loadMissing(['equipment', 'project.manager', 'site']);
        $custodian = User::query()->where('staff_id', $assignment->custodian_staff_id)->where('is_active', true)->first();
        $recipients = collect([$custodian, $assignment->project->manager])
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
            ->values();

        $this->notifications->send($recipients, $this->payload(
            $assignment,
            'info',
            'Equipment assigned',
            sprintf('%s was handed over for work at %s.', $assignment->equipment->asset_code, $assignment->site->name),
        ));
    }

    public function returned(EquipmentAssignment $assignment): void
    {
        $assignment->loadMissing(['equipment', 'handedOverBy']);
        $custodian = User::query()->where('staff_id', $assignment->custodian_staff_id)->where('is_active', true)->first();
        $recipients = collect([$custodian, $assignment->handedOverBy])
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
            ->values();

        $this->notifications->send($recipients, $this->payload(
            $assignment,
            'success',
            'Equipment return accepted',
            sprintf('%s was returned and its custody assignment was closed.', $assignment->equipment->asset_code),
        ));
    }

    /** @return array<string, mixed> */
    private function payload(EquipmentAssignment $assignment, string $severity, string $title, string $message): array
    {
        return [
            'tenant_id' => $assignment->tenant_id,
            'branch_id' => $assignment->branch_id,
            'project_id' => $assignment->project_id,
            'site_id' => $assignment->site_id,
            'equipment_id' => $assignment->equipment_id,
            'equipment_assignment_id' => $assignment->id,
            'category' => 'equipment_assignment',
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => '/equipment/'.$assignment->equipment_id.'?tab=assignments',
        ];
    }
}
