<?php

declare(strict_types=1);

namespace App\Actions\Operations\Expenses;

use App\Enums\ExpensePaymentStatus;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordExpensePayment
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{paid_at: string, amount: numeric-string, payment_method: string, reference?: string|null, notes?: string|null} $data */
    public function handle(Expense $expense, array $data, User $actor, bool $allowDraft = false): ExpensePayment
    {
        return DB::transaction(function () use ($actor, $allowDraft, $data, $expense): ExpensePayment {
            $expense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if (! $allowDraft && $expense->status->value !== 'approved') {
                throw ValidationException::withMessages(['payment' => 'Payments can only be recorded against an approved expense.']);
            }

            $amount = (float) $data['amount'];
            if ($amount > $expense->balance() + 0.0001) {
                throw ValidationException::withMessages(['amount' => 'The payment cannot exceed the outstanding balance.']);
            }

            $payment = ExpensePayment::query()->create([
                'tenant_id' => $expense->tenant_id,
                'branch_id' => $expense->branch_id,
                'expense_id' => $expense->id,
                'payment_number' => 'PAY-'.now()->format('Ym').'-'.mb_strtoupper(str()->random(6)),
                'paid_at' => $data['paid_at'],
                'amount' => $amount,
                'currency_code' => $expense->currency_code,
                'base_currency_amount' => $amount * (float) $expense->exchange_rate,
                'exchange_rate' => $expense->exchange_rate,
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => ExpensePaymentStatus::Recorded,
                'recorded_by' => $actor->id,
            ]);
            $this->auditLogger->record('expenses.payment.recorded', $payment, $actor, [], $payment->toArray(), branch: $expense->branch);

            return $payment;
        });
    }
}
