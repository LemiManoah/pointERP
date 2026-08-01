<?php

declare(strict_types=1);

namespace App\Actions\AccessControl\Users;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateAccessUser
{
    /**
     * @param  array{staff_id: string, password?: string|null, is_active?: bool, is_director?: bool, roles?: list<string>, permissions?: list<string>}  $data
     */
    public function handle(User $user, array $data): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $staff = Staff::query()->findOrFail($data['staff_id']);
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

            return $user;
        });
    }
}
