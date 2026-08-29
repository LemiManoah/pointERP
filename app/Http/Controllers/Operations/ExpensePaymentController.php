<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Expenses\RecordExpensePayment;
use App\Http\Requests\Operations\Expenses\StoreExpensePaymentRequest;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ExpensePaymentController
{
    public function __invoke(StoreExpensePaymentRequest $request, Expense $expense, RecordExpensePayment $action): RedirectResponse
    {
        Gate::authorize('recordPayment', $expense);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        /** @var array{paid_at: string, amount: numeric-string, payment_method: string, reference?: string|null, notes?: string|null} $data */
        $data = $request->validated();
        $action->handle($expense, $data, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense payment recorded.']);

        return to_route('expenses.show', $expense);
    }
}
