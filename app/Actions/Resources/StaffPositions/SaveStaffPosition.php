<?php

declare(strict_types=1);

namespace App\Actions\Resources\StaffPositions;

use App\Models\StaffPosition;
use App\Services\TenantContext;

final readonly class SaveStaffPosition
{
    public function __construct(private TenantContext $tenantContext)
    {
        //
    }

    /**
     * @param  array{name: string, code: string, is_active?: bool}  $data
     */
    public function handle(array $data, ?StaffPosition $staffPosition = null): StaffPosition
    {
        $tenant = $this->tenantContext->current();

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
