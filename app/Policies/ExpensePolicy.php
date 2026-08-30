<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ExpenseStatus;
use App\Models\DocumentLink;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;
use Illuminate\Support\Facades\Gate;

final class ExpensePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        if (! $this->belongsToSameTenant($user, $expense->tenant_id) || ! $this->canAccessBranch($user, $expense->branch_id) || ! $this->viewAny($user)) {
            return false;
        }

        $projectIds = $expense->lines()
            ->reorder()
            ->whereNotNull('project_id')
            ->distinct()
            ->pluck('project_id');
        $projects = Project::query()->whereIn('id', $projectIds)->get();

        return $projects->every(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project));
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense)
            && $expense->isEditable()
            && $expense->daily_site_report_id === null
            && $user->can('expenses.update');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $expense->daily_site_report_id === null && $expense->status === ExpenseStatus::Draft && ! $expense->payments()->exists() && ! DocumentLink::query()->where('linkable_type', Expense::class)->where('linkable_id', $expense->id)->exists() && $user->can('expenses.delete-drafts');
    }

    public function submit(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $expense->isEditable() && $user->can('expenses.submit');
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $expense->status === ExpenseStatus::Submitted && $user->can('expenses.approve');
    }

    public function reject(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $expense->status === ExpenseStatus::Submitted && $user->can('expenses.reject');
    }

    public function cancel(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Submitted, ExpenseStatus::Rejected], true) && $user->can('expenses.cancel');
    }

    public function recordPayment(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $expense->status === ExpenseStatus::Approved && $expense->balance() > 0 && $user->can('expense-payments.record');
    }

    public function viewCosts(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $user->can('expenses.view-costs');
    }
}
