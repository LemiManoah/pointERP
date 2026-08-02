<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Users;

use App\Models\Staff;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class CreateAccessUser
{
    public function __construct(private TenantContext $tenantContext)
    {
        //
    }

    /**
     * @param  array{staff_id: string, password: string, is_active?: bool, is_director?: bool, roles?: list<string>, permissions?: list<string>, branch_ids?: list<string>, default_branch_id?: string|null}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $tenant = $this->tenantContext->current();
            $staff = Staff::query()
                ->where('tenant_id', $tenant->id)
                ->findOrFail($data['staff_id']);

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'staff_id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'password' => $data['password'],
                'email_verified_at' => now(),
                'is_active' => $data['is_active'] ?? true,
                'is_director' => $data['is_director'] ?? false,
            ]);

            $user->syncRoles($data['roles'] ?? []);
            $user->syncPermissions($data['permissions'] ?? []);
            $this->syncBranches($user, $data['branch_ids'] ?? [$staff->branch_id], $data['default_branch_id'] ?? $staff->branch_id);

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
}
