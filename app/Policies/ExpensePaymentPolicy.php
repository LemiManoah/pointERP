<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ExpensePaymentStatus;
use App\Models\ExpensePayment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ExpensePaymentPolicy
{
    public function view(User $user, ExpensePayment $payment): bool
    {
        return $user->can('expense-payments.view') && Gate::forUser($user)->allows('view', $payment->expense);
    }

    public function reverse(User $user, ExpensePayment $payment): bool
    {
        return $this->view($user, $payment) && $payment->status === ExpensePaymentStatus::Recorded && $user->can('expense-payments.reverse');
    }
}
