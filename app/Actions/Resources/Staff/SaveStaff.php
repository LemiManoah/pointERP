<?php

declare(strict_types=1);

namespace App\Actions\Resources\Staff;

use App\Actions\EnsureDefaultTenant;
use App\Models\Staff;
use Illuminate\Support\Str;

final class SaveStaff
{
    /**
     * @param  array{branch_id: string, staff_position_id: string, staff_number: string, name: string, email: string, phone?: string|null, status: string}  $data
     */
    public function handle(array $data, ?Staff $staff = null): Staff
    {
        $tenant = resolve(EnsureDefaultTenant::class)->handle();

        $attributes = [
            'tenant_id' => $tenant->id,
            'branch_id' => $data['branch_id'],
            'staff_position_id' => $data['staff_position_id'],
            'staff_number' => mb_strtoupper($data['staff_number']),
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ];

        if ($staff instanceof Staff) {
            $staff->update($attributes);

            return $staff;
        }

        return Staff::query()->create($attributes);
    }
}
