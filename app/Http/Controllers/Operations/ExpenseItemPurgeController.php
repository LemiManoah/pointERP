<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\ExpenseItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpenseItemPurgeController
{
    public function __invoke(ExpenseItem $expenseItem, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('forceDelete', $expenseItem);

        if ($expenseItem->is_active || $expenseItem->lines()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only an inactive expense item with no recorded expense lines can be permanently deleted.']);

            return to_route('expenses.index', ['tab' => 'items']);
        }

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $auditLogger->record('expenses.item.permanently_deleted', $expenseItem, $actor, $expenseItem->toArray(), []);
        $expenseItem->forceDelete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense item permanently deleted.']);

        return to_route('expenses.index', ['tab' => 'items']);
    }
}
