<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use App\Enums\StaffDeploymentStatus;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class EndActiveStaffDeployments
{
    public function __construct(private EndStaffDeployment $endDeployment) {}

    public function handle(Staff $staff, User $actor): void
    {
        DB::transaction(function () use ($actor, $staff): void {
            $deployments = $staff->deployments()
                ->where('status', StaffDeploymentStatus::Active->value)
                ->lockForUpdate()
                ->get();

            foreach ($deployments as $deployment) {
                $this->endDeployment->handle($deployment, $actor);
            }
        });
    }
}
