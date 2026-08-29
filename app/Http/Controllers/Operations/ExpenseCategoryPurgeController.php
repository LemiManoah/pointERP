<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpenseCategoryPurgeController
{
    public function __invoke(ExpenseCategory $expenseCategory, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('forceDelete', $expenseCategory);

        if ($expenseCategory->is_active || $expenseCategory->items()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only an inactive category with no expense items can be permanently deleted.']);

            return to_route('expenses.index', ['tab' => 'categories']);
        }

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $auditLogger->record('expenses.category.permanently_deleted', $expenseCategory, $actor, $expenseCategory->toArray(), []);
        $expenseCategory->forceDelete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense type permanently deleted.']);

        return to_route('expenses.index', ['tab' => 'categories']);
    }
}
