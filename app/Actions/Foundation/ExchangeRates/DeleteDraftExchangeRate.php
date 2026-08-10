<?php

declare(strict_types=1);

namespace App\Actions\Foundation\ExchangeRates;

use App\Models\ExchangeRate;
use App\Services\AuditLogger;
use InvalidArgumentException;

final readonly class DeleteDraftExchangeRate
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(ExchangeRate $exchangeRate): void
    {
        throw_if($exchangeRate->status !== ExchangeRate::STATUS_DRAFT, InvalidArgumentException::class, 'Only draft exchange rates can be deleted.');

        $oldValues = [
            'tenant_id' => $exchangeRate->tenant_id,
            'branch_id' => $exchangeRate->branch_id,
            'from_currency_code' => $exchangeRate->from_currency_code,
            'to_currency_code' => $exchangeRate->to_currency_code,
            'rate' => $exchangeRate->rate,
            'effective_date' => $exchangeRate->effective_date->toDateString(),
            'status' => $exchangeRate->status,
        ];

        $exchangeRate->delete();

        $this->auditLogger->record(
            event: 'currency.exchange_rate.deleted',
            subject: $exchangeRate,
            oldValues: $oldValues,
        );
    }
}
