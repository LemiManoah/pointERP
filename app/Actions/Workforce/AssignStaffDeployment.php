<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use App\Enums\StaffDeploymentStatus;
use App\Models\Project;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffDeployment;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignStaffDeployment
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {}

    /** @param array{staff_id: string, project_id: string, site_id?: string|null, workforce_trade_id?: string|null, starts_on: string, notes?: string|null} $data */
    public function handle(array $data, User $actor): StaffDeployment
    {
        $tenantId = $this->tenantContext->id();
        $startsOn = CarbonImmutable::parse($data['starts_on'])->toDateString();
        $staff = Staff::query()->where('tenant_id', $tenantId)->whereKey($data['staff_id'])->firstOrFail();
        $project = Project::query()->where('tenant_id', $tenantId)->whereKey($data['project_id'])->firstOrFail();
        $site = isset($data['site_id']) && $data['site_id'] !== ''
            ? Site::query()->where('tenant_id', $tenantId)->whereKey($data['site_id'])->firstOrFail()
            : null;
        $trade = isset($data['workforce_trade_id']) && $data['workforce_trade_id'] !== ''
            ? WorkforceTrade::query()->where('tenant_id', $tenantId)->whereKey($data['workforce_trade_id'])->firstOrFail()
            : null;

        if ($staff->branch_id !== $project->branch_id) {
            throw ValidationException::withMessages(['project_id' => 'The staff member and project must belong to the same branch.']);
        }

        if ($site instanceof Site && ($site->project_id !== $project->id || $site->branch_id !== $staff->branch_id)) {
            throw ValidationException::withMessages(['site_id' => 'The selected site must belong to the selected project and branch.']);
        }

        if ($trade instanceof WorkforceTrade && ! $trade->is_active) {
            throw ValidationException::withMessages(['workforce_trade_id' => 'The selected trade is inactive.']);
        }

        return DB::transaction(function () use ($actor, $data, $project, $site, $staff, $startsOn, $tenantId, $trade): StaffDeployment {
            Staff::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();

            $current = StaffDeployment::query()
                ->where('tenant_id', $tenantId)
                ->where('staff_id', $staff->id)
                ->where('status', StaffDeploymentStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($current instanceof StaffDeployment) {
                if ($startsOn < $current->starts_on->toDateString()) {
                    throw ValidationException::withMessages(['starts_on' => 'The new deployment cannot start before the current deployment.']);
                }

                $oldValues = $current->only(['status', 'ends_on', 'ended_by']);
                $current->update([
                    'status' => StaffDeploymentStatus::Ended,
                    'ends_on' => $startsOn,
                    'ended_by' => $actor->id,
                ]);
                $this->auditLogger->record('workforce.deployment.ended', $current, $actor, $oldValues, $current->fresh()?->toArray() ?? [], 'Ended by reassignment', $staff->branch);
            }

            $deployment = StaffDeployment::query()->create([
                'tenant_id' => $tenantId,
                'branch_id' => $staff->branch_id,
                'staff_id' => $staff->id,
                'project_id' => $project->id,
                'site_id' => $site?->id,
                'workforce_trade_id' => $trade instanceof WorkforceTrade ? $trade->id : $staff->primary_trade_id,
                'starts_on' => $startsOn,
                'ends_on' => null,
                'status' => StaffDeploymentStatus::Active,
                'notes' => $data['notes'] ?? null,
                'assigned_by' => $actor->id,
                'ended_by' => null,
            ]);

            $this->auditLogger->record('workforce.deployment.created', $deployment, $actor, [], $deployment->fresh()?->toArray() ?? [], branch: $staff->branch);

            return $deployment;
        });
    }
}
