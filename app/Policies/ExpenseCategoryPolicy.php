<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class ExpenseCategoryPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $this->belongsToSameTenant($user, $category->tenant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('expense-categories.manage');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $this->view($user, $category) && $this->create($user);
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $this->update($user, $category);
    }

    public function forceDelete(User $user, ExpenseCategory $category): bool
    {
        return $this->update($user, $category) && ! $category->is_active;
    }
}
