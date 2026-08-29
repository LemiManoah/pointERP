<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Expenses\ReverseExpensePayment;
use App\Http\Requests\Operations\Expenses\ReverseExpensePaymentRequest;
use App\Models\ExpensePayment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpensePaymentReversalController
{
    public function __invoke(ReverseExpensePaymentRequest $request, ExpensePayment $expensePayment, ReverseExpensePayment $action): RedirectResponse
    {
        Gate::authorize('reverse', $expensePayment);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($expensePayment, (string) $request->validated('reason'), $actor);
        Inertia::flash('toast', ['type' => 'warning', 'message' => 'Expense payment reversed.']);

        return to_route('expenses.show', $expensePayment->expense_id);
    }
}
