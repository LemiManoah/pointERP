<?php

declare(strict_types=1);

namespace App\Actions\Resources\Staff;

use App\Models\Staff;

final class ToggleStaffStatus
{
    public function handle(Staff $staff): Staff
    {
        $staff->update([
            'status' => $staff->status === 'active' ? 'inactive' : 'active',
        ]);

        return $staff;
    }
}
