<?php

declare(strict_types=1);

namespace App\Actions\Resources\Staff;

use App\Models\Staff;
use App\Services\TenantContext;
use Illuminate\Support\Str;

final readonly class SaveStaff
{
    public function __construct(private TenantContext $tenantContext)
    {
        //
    }

    /**
     * @param  array{branch_id: string, staff_position_id: string, staff_number?: string|null, name: string, email: string, phone?: string|null, status: string}  $data
     */
    public function handle(array $data, ?Staff $staff = null): Staff
    {
        $tenant = $this->tenantContext->current();

        $attributes = [
            'tenant_id' => $tenant->id,
            'branch_id' => $data['branch_id'],
            'staff_position_id' => $data['staff_position_id'],
            'staff_number' => $this->staffNumber($data['staff_number'] ?? null),
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
