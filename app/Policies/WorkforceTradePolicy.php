<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkforceTrade;
use App\Policies\Concerns\ChecksTenantAccess;

final class WorkforceTradePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('workforce.view')) {
            return true;
        }

        return $user->can('workforce.trades.manage');
    }

    public function view(User $user, WorkforceTrade $trade): bool
    {
        return $this->belongsToSameTenant($user, $trade->tenant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('workforce.trades.manage');
    }

    public function update(User $user, WorkforceTrade $trade): bool
    {
        return $this->view($user, $trade) && $this->create($user);
    }

    public function delete(User $user, WorkforceTrade $trade): bool
    {
        return $this->update($user, $trade);
    }
}
