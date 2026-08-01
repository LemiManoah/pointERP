<?php

declare(strict_types=1);

namespace App\Actions\Foundation\Currencies;

use App\Models\Currency;

final class CreateCurrency
{
    /**
     * @param  array{code: string, name: string, symbol?: string|null, decimal_places: int|string, is_active?: bool}  $data
     */
    public function handle(array $data): Currency
    {
        return Currency::query()->create([
            'code' => mb_strtoupper($data['code']),
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? null,
            'decimal_places' => (int) $data['decimal_places'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
