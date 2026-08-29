<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpensePayeeType;
use App\Enums\ExpensePaymentStatus;
use App\Enums\ExpenseStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $expense_number
 * @property-read CarbonInterface $expense_date
 * @property-read ExpensePayeeType $payee_type
 * @property-read string|null $customer_id
 * @property-read string|null $staff_id
 * @property-read string $payee_name_snapshot
 * @property-read string $currency_code
 * @property-read string $base_currency_code
 * @property-read string $exchange_rate
 * @property-read string $total_amount
 * @property-read string $base_currency_total
 * @property-read string|null $description
 * @property-read string|null $reference
 * @property-read ExpenseStatus $status
 * @property-read Branch $branch
 * @property-read Customer|null $customer
 * @property-read Staff|null $staff
 */
#[Fillable(['tenant_id', 'branch_id', 'expense_number', 'expense_date', 'payee_type', 'customer_id', 'staff_id', 'payee_name_snapshot', 'currency_code', 'base_currency_code', 'exchange_rate_id', 'exchange_rate', 'subtotal', 'total_amount', 'base_currency_total', 'description', 'reference', 'status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'cancelled_by', 'cancelled_at', 'decision_reason', 'corrects_expense_id', 'created_by', 'updated_by'])]
final class Expense extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<Expense>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'expense_date' => 'date',
            'payee_type' => ExpensePayeeType::class,
            'exchange_rate' => 'decimal:10',
            'subtotal' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'base_currency_total' => 'decimal:4',
            'status' => ExpenseStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** @return HasMany<ExpenseLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class)->orderBy('sort_order');
    }

    /** @return HasMany<ExpensePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class)->latest('paid_at');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ExpenseStatus::Draft, ExpenseStatus::Rejected], true);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->where('status', ExpensePaymentStatus::Recorded)->sum('amount');
    }

    public function balance(): float
    {
        return max((float) $this->total_amount - $this->paidAmount(), 0.0);
    }

    public function paymentStatus(): string
    {
        $paid = $this->paidAmount();

        return match (true) {
            $paid <= 0 => 'unpaid',
            $paid + 0.0001 >= (float) $this->total_amount => 'paid',
            default => 'partially_paid',
        };
    }
}
