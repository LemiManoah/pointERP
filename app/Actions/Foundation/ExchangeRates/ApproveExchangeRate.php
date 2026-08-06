<?php

declare(strict_types=1);

namespace App\Actions\Foundation\ExchangeRates;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ApproveExchangeRate
{
    public function handle(ExchangeRate $exchangeRate, User $actor): ExchangeRate
    {
        throw_if($exchangeRate->status !== ExchangeRate::STATUS_DRAFT, InvalidArgumentException::class, 'Only draft exchange rates can be approved.');

        return DB::transaction(function () use ($actor, $exchangeRate): ExchangeRate {
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

            return $exchangeRate;
        });
    }
}
