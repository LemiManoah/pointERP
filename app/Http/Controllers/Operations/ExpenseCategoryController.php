<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Expenses\SaveExpenseCategory;
use App\Http\Requests\Operations\Expenses\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpenseCategoryController
{
    public function store(StoreExpenseCategoryRequest $request, SaveExpenseCategory $action): RedirectResponse
    {
        Gate::authorize('create', ExpenseCategory::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense type saved.']);

        return to_route('expenses.index', ['tab' => 'categories']);
    }

    public function update(StoreExpenseCategoryRequest $request, ExpenseCategory $expenseCategory, SaveExpenseCategory $action): RedirectResponse
    {
        Gate::authorize('update', $expenseCategory);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $expenseCategory);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense type updated.']);

        return to_route('expenses.index', ['tab' => 'categories']);
    }

    public function destroy(ExpenseCategory $expenseCategory, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $expenseCategory);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        if ($expenseCategory->is_active && $expenseCategory->items()->where('is_active', true)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Deactivate the active expense items in this category first.']);

            return to_route('expenses.index', ['tab' => 'categories']);
        }

        $old = ['is_active' => $expenseCategory->is_active];
        $expenseCategory->update(['is_active' => ! $expenseCategory->is_active, 'updated_by' => $actor->id]);
        $auditLogger->record('expenses.category.status_changed', $expenseCategory, $actor, $old, ['is_active' => $expenseCategory->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $expenseCategory->is_active ? 'Expense type restored.' : 'Expense type deactivated.']);

        return to_route('expenses.index', ['tab' => 'categories']);
    }
}
