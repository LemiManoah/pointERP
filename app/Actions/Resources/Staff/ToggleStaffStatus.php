<?php

declare(strict_types=1);

namespace App\Actions\Resources\Staff;

use App\Enums\StaffDeploymentStatus;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

final readonly class ToggleStaffStatus
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Staff $staff, User $actor): Staff
    {
        if ($staff->status === 'active' && $staff->deployments()->where('status', StaffDeploymentStatus::Active->value)->exists()) {
            throw ValidationException::withMessages([
                'staff' => 'End the current workforce deployment before deactivating this staff member.',
            ]);
        }

        $oldValues = ['status' => $staff->status];
        $staff->update([
            'status' => $staff->status === 'active' ? 'inactive' : 'active',
        ]);
        $this->auditLogger->record(
            'resources.staff.status_changed',
            $staff,
            $actor,
            $oldValues,
            ['status' => $staff->status],
            branch: $staff->branch,
        );

        return $staff;
    }
}
