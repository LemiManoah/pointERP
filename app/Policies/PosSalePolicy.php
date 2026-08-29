<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PosSale;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class PosSalePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('pos.view');
    }

    public function view(User $user, PosSale $sale): bool
    {
        return $this->belongsToSameTenant($user, $sale->tenant_id)
            && $this->canAccessBranch($user, $sale->branch_id)
            && $this->viewAny($user)
            && ($user->can('pos.view-all-sales') || $sale->sold_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('pos.sell');
    }

    public function recordPayment(User $user, PosSale $sale): bool
    {
        return $this->view($user, $sale)
            && $user->can('pos.record-payment')
            && $sale->status->value === 'completed'
            && (float) $sale->balance_due > 0;
    }
}
