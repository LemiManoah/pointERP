<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class ExchangeRatePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('exchange-rates.view');
    }

    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->belongsToSameTenant($user, $exchangeRate->tenant_id)
            && $this->canAccessBranch($user, $exchangeRate->branch_id)
            && $user->can('exchange-rates.view');
    }

    public function create(User $user): bool
    {
        return $user->can('exchange-rates.create');
    }

    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->belongsToSameTenant($user, $exchangeRate->tenant_id)
            && $this->canAccessBranch($user, $exchangeRate->branch_id)
            && $exchangeRate->status === ExchangeRate::STATUS_DRAFT
            && $user->can('exchange-rates.update');
    }

    public function approve(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->belongsToSameTenant($user, $exchangeRate->tenant_id)
            && $this->canAccessBranch($user, $exchangeRate->branch_id)
            && $exchangeRate->status === ExchangeRate::STATUS_DRAFT
            && $user->can('exchange-rates.approve');
    }

    public function delete(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->update($user, $exchangeRate);
    }
}
