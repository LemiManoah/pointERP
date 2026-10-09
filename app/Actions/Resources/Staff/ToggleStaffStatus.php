<?php

declare(strict_types=1);

namespace App\Actions\Resources\Staff;

use App\Actions\Workforce\EndActiveStaffDeployments;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final readonly class ToggleStaffStatus
{
    public function __construct(
        private AuditLogger $auditLogger,
        private EndActiveStaffDeployments $endDeployments,
    ) {}

    public function handle(Staff $staff, User $actor): Staff
    {
        return DB::transaction(function () use ($actor, $staff): Staff {
            if ($staff->status === 'active') {
                $this->endDeployments->handle($staff, $actor);
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
        });
    }
}
