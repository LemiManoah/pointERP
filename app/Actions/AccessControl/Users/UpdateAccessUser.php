<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Users;

use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAccessUser
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{staff_id: string, password?: string|null, is_active?: bool, is_director?: bool, roles?: list<string>, permissions?: list<string>, branch_ids?: list<string>, default_branch_id?: string|null}  $data
     */
    public function handle(User $user, array $data): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $user->load(['branches', 'roles', 'permissions', 'staff.branch']);
            $oldValues = $this->snapshot($user);
            $staff = Staff::query()
                ->where('tenant_id', $this->tenantContext->id())
                ->with('branch')
                ->findOrFail($data['staff_id']);
            $attributes = [
                'staff_id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'is_active' => $data['is_active'] ?? true,
                'is_director' => $data['is_director'] ?? false,
            ];

            if (($data['password'] ?? null) !== null && $data['password'] !== '') {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);
            $user->syncRoles($data['roles'] ?? []);
            $user->syncPermissions($data['permissions'] ?? []);
            $this->syncBranches($user, $data['branch_ids'] ?? [$staff->branch_id], $data['default_branch_id'] ?? $staff->branch_id);
            $user->refresh()->load(['branches', 'roles', 'permissions']);

            $this->auditLogger->record(
                event: 'access.user.updated',
                subject: $user,
                oldValues: $oldValues,
                newValues: $this->snapshot($user),
                branch: $staff->branch,
            );

            return $user;
        });
    }

    /**
     * @param  list<string>  $branchIds
     */
    private function syncBranches(User $user, array $branchIds, ?string $defaultBranchId): void
    {
        $branchIds = array_values(array_unique($branchIds));
        $defaultBranchId = in_array($defaultBranchId, $branchIds, true) ? $defaultBranchId : $branchIds[0];

        $user->branches()->sync(
            collect($branchIds)
                ->mapWithKeys(fn (string $branchId): array => [
                    $branchId => ['is_default' => $branchId === $defaultBranchId],
                ])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'id' => $user->id,
            'staff_id' => $user->staff_id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_director' => $user->is_director,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getDirectPermissions()->pluck('name')->values()->all(),
            'branch_ids' => $user->branches->pluck('id')->values()->all(),
            'default_branch_id' => $user->branches()
                ->wherePivot('is_default', true)
                ->value('branches.id'),
        ];
    }
}
