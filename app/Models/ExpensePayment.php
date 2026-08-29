<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpensePaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $expense_id
 * @property-read string $payment_number
 * @property-read CarbonInterface $paid_at
 * @property-read string $amount
 * @property-read string $currency_code
 * @property-read ExpensePaymentMethod $payment_method
 * @property-read ExpensePaymentStatus $status
 */
#[Fillable(['tenant_id', 'branch_id', 'expense_id', 'payment_number', 'paid_at', 'amount', 'currency_code', 'base_currency_amount', 'exchange_rate', 'payment_method', 'reference', 'notes', 'status', 'reverses_payment_id', 'recorded_by', 'reversed_by', 'reversed_at', 'reversal_reason'])]
final class ExpensePayment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ExpensePayment>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount' => 'decimal:4',
            'base_currency_amount' => 'decimal:4',
            'exchange_rate' => 'decimal:10',
            'payment_method' => ExpensePaymentMethod::class,
            'status' => ExpensePaymentStatus::class,
            'reversed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
