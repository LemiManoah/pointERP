<?php

declare(strict_types=1);

namespace App\Actions\Operations\Pos;

use App\Enums\PosPaymentMethod;
use App\Enums\PosPaymentStatus;
use App\Enums\PosSalePaymentStatus;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\User;
use App\Services\AuditLogger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RecordPosPayment
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(PosSale $sale, array $data, User $actor): PosPayment
    {
        return DB::transaction(function () use ($actor, $data, $sale): PosPayment {
            $lockedSale = PosSale::query()->lockForUpdate()->findOrFail($sale->id);
            $amount = BigDecimal::of((string) $data['amount']);
            $balance = BigDecimal::of($lockedSale->balance_due);
            $method = PosPaymentMethod::from((string) $data['method']);

            if ($amount->isGreaterThan($balance)) {
                throw ValidationException::withMessages(['amount' => 'The payment cannot exceed the outstanding balance of '.$balance->toScale(4).'.']);
            }

            if ($method !== PosPaymentMethod::Cash && blank($data['reference'] ?? null)) {
                throw ValidationException::withMessages(['reference' => 'Enter a reference for non-cash payments.']);
            }

            $oldValues = $lockedSale->only(['amount_paid', 'balance_due', 'payment_status']);
            $payment = PosPayment::query()->create([
                'tenant_id' => $lockedSale->tenant_id,
                'branch_id' => $lockedSale->branch_id,
                'pos_sale_id' => $lockedSale->id,
                'payment_number' => $this->number(),
                'method' => $method,
                'amount' => (string) $amount->toScale(4),
                'currency_code' => $lockedSale->currency_code,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => PosPaymentStatus::Recorded,
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);

            $paid = BigDecimal::of($lockedSale->amount_paid)->plus($amount);
            $remaining = BigDecimal::of($lockedSale->total_amount)->minus($paid);
            $lockedSale->update([
                'amount_paid' => (string) $paid->toScale(4),
                'balance_due' => (string) $remaining->toScale(4),
                'payment_status' => $remaining->isZero()
                    ? PosSalePaymentStatus::Paid
                    : PosSalePaymentStatus::PartiallyPaid,
            ]);

            $freshValues = $lockedSale->fresh()?->only(['amount_paid', 'balance_due', 'payment_status']) ?? [];

            $this->auditLogger->record('pos.payment.recorded', $lockedSale, $actor, $oldValues, [
                ...$freshValues,
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
            ], branch: $lockedSale->branch);

            return $payment;
        });
    }

    private function number(): string
    {
        return 'PAY-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(5));
    }
}
