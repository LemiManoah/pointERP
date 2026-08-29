<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Expenses\TransitionExpense;
use App\Enums\ExpenseStatus;
use App\Http\Requests\Operations\Expenses\ExpenseDecisionRequest;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpenseRejectionController
{
    public function __invoke(ExpenseDecisionRequest $request, Expense $expense, TransitionExpense $action): RedirectResponse
    {
        Gate::authorize('reject', $expense);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($expense, ExpenseStatus::Rejected, $actor, (string) $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'warning', 'message' => 'Expense rejected and returned for correction.']);

        return to_route('expenses.show', $expense);
    }
}
