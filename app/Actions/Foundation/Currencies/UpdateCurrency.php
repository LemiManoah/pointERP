<?php

declare(strict_types=1);

namespace App\Actions\Foundation\Currencies;

use App\Models\Currency;

final class UpdateCurrency
{
    /**
     * @param  array{code: string, name: string, symbol?: string|null, decimal_places: int|string, is_active?: bool}  $data
     */
    public function handle(Currency $currency, array $data): Currency
    {
        $currency->update([
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? null,
            'decimal_places' => (int) $data['decimal_places'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $currency;
    }
}
