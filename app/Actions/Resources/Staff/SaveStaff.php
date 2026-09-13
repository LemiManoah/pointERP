<?php

declare(strict_types=1);

namespace App\Actions\Resources\Staff;

use App\Enums\StaffDeploymentStatus;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveStaff
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param array{
     *   branch_id: string,
     *   staff_position_id: string,
     *   employment_type: string,
     *   primary_trade_id?: string|null,
     *   staff_number?: string|null,
     *   name: string,
     *   email: string,
     *   phone?: string|null,
     *   status: string
     * } $data
     */
    public function handle(array $data, User $actor, ?Staff $staff = null): Staff
    {
        if ($staff instanceof Staff && $staff->deployments()->where('status', StaffDeploymentStatus::Active->value)->exists()) {
            if ($staff->branch_id !== $data['branch_id']) {
                throw ValidationException::withMessages([
                    'branch_id' => 'End the current workforce deployment before moving this staff member to another branch.',
                ]);
            }

            if ($data['status'] === 'inactive') {
                throw ValidationException::withMessages([
                    'status' => 'End the current workforce deployment before deactivating this staff member.',
                ]);
            }
        }

        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'branch_id' => $data['branch_id'],
            'staff_position_id' => $data['staff_position_id'],
            'employment_type' => $data['employment_type'],
            'primary_trade_id' => $data['primary_trade_id'] ?? null,
            'staff_number' => $this->staffNumber($data['staff_number'] ?? null),
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ];

        $oldValues = $staff?->only(array_keys($attributes)) ?? [];

        if ($staff instanceof Staff) {
            $staff->update($attributes);
            $event = 'resources.staff.updated';
        } else {
            $staff = Staff::query()->create($attributes);
            $event = 'resources.staff.created';
        }

        $this->auditLogger->record($event, $staff, $actor, $oldValues, $staff->fresh()?->toArray() ?? []);

        return $staff;
    }

    private function staffNumber(?string $requested): string
    {
        $requested = mb_strtoupper(mb_trim((string) $requested));

        if ($requested !== '') {
            return $requested;
        }

        do {
            $generated = 'STF-'.Str::upper(Str::random(6));
        } while (Staff::query()->where('tenant_id', $this->tenantContext->id())->where('staff_number', $generated)->exists());

        return $generated;
    }
}
