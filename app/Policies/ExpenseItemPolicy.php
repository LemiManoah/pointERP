<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpenseItem;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class ExpenseItemPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    public function view(User $user, ExpenseItem $item): bool
    {
        return $this->belongsToSameTenant($user, $item->tenant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('expense-items.manage');
    }

    public function update(User $user, ExpenseItem $item): bool
    {
        return $this->view($user, $item) && $this->create($user);
    }

    public function delete(User $user, ExpenseItem $item): bool
    {
        return $this->update($user, $item);
    }

    public function forceDelete(User $user, ExpenseItem $item): bool
    {
        return $this->update($user, $item) && ! $item->is_active;
    }
}
