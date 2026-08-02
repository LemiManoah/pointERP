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
     * @param  array{staff_id: string, password: string, is_active?: bool, is_director?: bool, roles?: list<string>, permissions?: list<string>}  $data
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
            $user->branches()->syncWithoutDetaching([
                $staff->branch_id => ['is_default' => true],
            ]);

            return $user;
        });
    }
}
