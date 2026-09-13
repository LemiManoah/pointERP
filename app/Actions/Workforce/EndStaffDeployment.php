<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use App\Enums\StaffDeploymentStatus;
use App\Models\StaffDeployment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class EndStaffDeployment
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(StaffDeployment $deployment, User $actor): StaffDeployment
    {
        return DB::transaction(function () use ($actor, $deployment): StaffDeployment {
            $locked = StaffDeployment::query()->whereKey($deployment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StaffDeploymentStatus::Active) {
                throw ValidationException::withMessages(['deployment' => 'This deployment has already ended.']);
            }

            $oldValues = $locked->only(['status', 'ends_on', 'ended_by']);
            $locked->update([
                'status' => StaffDeploymentStatus::Ended,
                'ends_on' => now()->toDateString(),
                'ended_by' => $actor->id,
            ]);
            $this->auditLogger->record('workforce.deployment.ended', $locked, $actor, $oldValues, $locked->fresh()?->toArray() ?? [], branch: $locked->branch);

            return $locked;
        });
    }
}
