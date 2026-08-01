<?php

declare(strict_types=1);

namespace App\Actions\Foundation\Currencies;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

final class ToggleCurrencyStatus
{
    public function handle(Currency $currency): Currency
    {
        if ($currency->is_active) {
            $this->ensureCurrencyCanBeDisabled($currency);
        }

        $currency->update([
            'is_active' => ! $currency->is_active,
        ]);

        return $currency;
    }

    private function ensureCurrencyCanBeDisabled(Currency $currency): void
    {
        $isDefaultCurrency = Country::query()
            ->where('default_currency_code', $currency->code)
            ->exists()
            || Tenant::query()
                ->where('default_currency_code', $currency->code)
                ->exists();

        if (! $isDefaultCurrency) {
            return;
        }

        throw ValidationException::withMessages([
            'currency' => 'This currency is still used as a default currency.',
        ]);
    }
}
