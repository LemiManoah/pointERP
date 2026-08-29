<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Expenses\SaveExpenseItem;
use App\Http\Requests\Operations\Expenses\StoreExpenseItemRequest;
use App\Models\ExpenseItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpenseItemController
{
    public function store(StoreExpenseItemRequest $request, SaveExpenseItem $action): RedirectResponse
    {
        Gate::authorize('create', ExpenseItem::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense item saved.']);

        return to_route('expenses.index', ['tab' => 'items']);
    }

    public function update(StoreExpenseItemRequest $request, ExpenseItem $expenseItem, SaveExpenseItem $action): RedirectResponse
    {
        Gate::authorize('update', $expenseItem);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $expenseItem);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense item updated.']);

        return to_route('expenses.index', ['tab' => 'items']);
    }

    public function destroy(ExpenseItem $expenseItem, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $expenseItem);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $expenseItem->loadMissing('category');
        if (! $expenseItem->is_active && ! $expenseItem->category->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Restore the expense type before restoring this item.']);

            return to_route('expenses.index', ['tab' => 'items']);
        }

        $old = ['is_active' => $expenseItem->is_active];
        $expenseItem->update(['is_active' => ! $expenseItem->is_active, 'updated_by' => $actor->id]);
        $auditLogger->record('expenses.item.status_changed', $expenseItem, $actor, $old, ['is_active' => $expenseItem->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $expenseItem->is_active ? 'Expense item restored.' : 'Expense item deactivated.']);

        return to_route('expenses.index', ['tab' => 'items']);
    }
}
