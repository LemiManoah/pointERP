<?php

declare(strict_types=1);

namespace App\Actions\Resources\StaffPositions;

use App\Models\StaffPosition;

final class ToggleStaffPositionStatus
{
    public function handle(StaffPosition $staffPosition): StaffPosition
    {
        $staffPosition->update([
            'is_active' => ! $staffPosition->is_active,
        ]);

        return $staffPosition;
    }
}
