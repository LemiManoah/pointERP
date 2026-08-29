<?php

declare(strict_types=1);

namespace App\Actions\Operations\Expenses;

use App\Enums\ExpensePaymentStatus;
use App\Models\ExpensePayment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReverseExpensePayment
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(ExpensePayment $payment, string $reason, User $actor): ExpensePayment
    {
        return DB::transaction(function () use ($actor, $payment, $reason): ExpensePayment {
            $payment = ExpensePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== ExpensePaymentStatus::Recorded) {
                throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
            }

            $old = $payment->toArray();
            $payment->update(['status' => ExpensePaymentStatus::Reversed, 'reversed_by' => $actor->id, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            $this->auditLogger->record('expenses.payment.reversed', $payment, $actor, $old, $payment->fresh()?->toArray() ?? [], $reason, $payment->expense->branch);

            return $payment;
        });
    }
}
