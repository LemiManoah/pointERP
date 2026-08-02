<?php

declare(strict_types=1);

namespace App\Actions\Foundation\ExchangeRates;

use App\Models\ExchangeRate;
use InvalidArgumentException;

final readonly class DeleteDraftExchangeRate
{
    public function handle(ExchangeRate $exchangeRate): void
    {
        if ($exchangeRate->status !== ExchangeRate::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft exchange rates can be deleted.');
        }

        $exchangeRate->delete();
    }
}
