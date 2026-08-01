<?php

declare(strict_types=1);

namespace App\Actions\Resources\StaffPositions;

use App\Actions\EnsureDefaultTenant;
use App\Models\StaffPosition;

final class SaveStaffPosition
{
    /**
     * @param  array{name: string, code: string, is_active?: bool}  $data
     */
    public function handle(array $data, ?StaffPosition $staffPosition = null): StaffPosition
    {
        $tenant = resolve(EnsureDefaultTenant::class)->handle();

        $attributes = [
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'code' => mb_strtoupper($data['code']),
            'is_active' => $data['is_active'] ?? true,
        ];

        if ($staffPosition instanceof StaffPosition) {
            $staffPosition->update($attributes);

            return $staffPosition;
        }

        return StaffPosition::query()->create($attributes);
    }
}
