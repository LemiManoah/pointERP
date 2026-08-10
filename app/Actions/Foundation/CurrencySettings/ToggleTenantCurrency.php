<?php

declare(strict_types=1);

namespace App\Actions\Foundation\CurrencySettings;

use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\Currency;
use App\Models\TenantCurrency;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ToggleTenantCurrency
{
    public function __construct(private TenantContext $tenantContext)
    {
        //
    }

    public function handle(Currency $currency): TenantCurrency
    {
        $tenant = $this->tenantContext->current();

        return DB::transaction(function () use ($currency, $tenant): TenantCurrency {
            $setting = TenantCurrency::withTrashed()->firstOrNew([
                'tenant_id' => $tenant->id,
                'currency_code' => $currency->code,
            ]);

            throw_if($setting->exists && $setting->is_enabled && $setting->is_default, InvalidArgumentException::class, 'The tenant default currency cannot be disabled.');

            throw_if($setting->exists && $setting->is_enabled && Branch::query()
                ->where('tenant_id', $tenant->id)
                ->where('default_currency_code', $currency->code)
                ->where('status', 'active')
                ->exists(), InvalidArgumentException::class, 'A branch base currency cannot be disabled at tenant level.');

            $setting->forceFill([
                'tenant_id' => $tenant->id,
                'currency_code' => $currency->code,
                'is_enabled' => ! $setting->exists || ! $setting->is_enabled,
                'is_default' => $currency->code === $tenant->default_currency_code || $setting->is_default,
            ]);

            if ($setting->trashed()) {
                $setting->restore();
            }

            $setting->save();

            if (! $setting->is_enabled) {
                BranchCurrency::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('currency_code', $currency->code)
                    ->update([
                        'is_enabled' => false,
                        'is_default_transaction_currency' => false,
                    ]);
            }

            return $setting;
        });
    }
}
