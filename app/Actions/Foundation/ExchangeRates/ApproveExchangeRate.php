<?php

declare(strict_types=1);

namespace App\Actions\Foundation\ExchangeRates;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ApproveExchangeRate
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(ExchangeRate $exchangeRate, User $actor): ExchangeRate
    {
        throw_if($exchangeRate->status !== ExchangeRate::STATUS_DRAFT, InvalidArgumentException::class, 'Only draft exchange rates can be approved.');

        return DB::transaction(function () use ($actor, $exchangeRate): ExchangeRate {
            $oldValues = $this->snapshot($exchangeRate);

            ExchangeRate::query()
                ->where('tenant_id', $exchangeRate->tenant_id)
                ->where('branch_id', $exchangeRate->branch_id)
                ->where('from_currency_code', $exchangeRate->from_currency_code)
                ->where('to_currency_code', $exchangeRate->to_currency_code)
                ->where('status', ExchangeRate::STATUS_APPROVED)
                ->where('effective_date', '<=', $exchangeRate->effective_date)
                ->update([
                    'status' => ExchangeRate::STATUS_SUPERSEDED,
                    'updated_by' => $actor->id,
                ]);

            $exchangeRate->update([
                'status' => ExchangeRate::STATUS_APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $this->auditLogger->record(
                event: 'currency.exchange_rate.approved',
                subject: $exchangeRate,
                actor: $actor,
                oldValues: $oldValues,
                newValues: $this->snapshot($exchangeRate),
            );

            return $exchangeRate;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ExchangeRate $exchangeRate): array
    {
        return [
            'tenant_id' => $exchangeRate->tenant_id,
            'branch_id' => $exchangeRate->branch_id,
            'from_currency_code' => $exchangeRate->from_currency_code,
            'to_currency_code' => $exchangeRate->to_currency_code,
            'rate' => $exchangeRate->rate,
            'effective_date' => $exchangeRate->effective_date->toDateString(),
            'expires_at' => $exchangeRate->expires_at?->toDateTimeString(),
            'status' => $exchangeRate->status,
            'approved_by' => $exchangeRate->approved_by,
            'approved_at' => $exchangeRate->approved_at?->toDateTimeString(),
            'updated_by' => $exchangeRate->updated_by,
        ];
    }
}
