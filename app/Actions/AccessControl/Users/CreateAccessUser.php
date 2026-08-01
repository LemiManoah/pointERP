<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Users;

use App\Actions\EnsureDefaultTenant;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateAccessUser
{
    /**
     * @param  array{staff_id: string, password: string, is_active?: bool, is_director?: bool, roles?: list<string>, permissions?: list<string>}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $tenant = resolve(EnsureDefaultTenant::class)->handle();
            $staff = Staff::query()->findOrFail($data['staff_id']);

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

            return $user;
        });
    }
}
