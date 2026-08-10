<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TenantCurrency;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class TenantCurrencyPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('currency-settings.manage');
    }

    public function view(User $user, TenantCurrency $tenantCurrency): bool
    {
        return $this->belongsToSameTenant($user, $tenantCurrency->tenant_id)
            && $user->can('currency-settings.manage');
    }

    public function update(User $user, TenantCurrency $tenantCurrency): bool
    {
        return $this->view($user, $tenantCurrency);
    }
}
