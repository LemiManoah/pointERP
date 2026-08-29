<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Expenses\TransitionExpense;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpenseSubmitController
{
    public function __invoke(Request $request, Expense $expense, TransitionExpense $action): RedirectResponse
    {
        Gate::authorize('submit', $expense);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($expense, ExpenseStatus::Submitted, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense submitted for approval.']);

        return to_route('expenses.show', $expense);
    }
}
