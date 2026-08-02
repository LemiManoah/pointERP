<?php

declare(strict_types=1);

namespace App\Actions\Foundation\CurrencySettings;

use App\Models\Currency;
use App\Models\Branch;
use App\Models\BranchCurrency;
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

            if ($setting->exists && $setting->is_enabled && $setting->is_default) {
                throw new InvalidArgumentException('The tenant default currency cannot be disabled.');
            }

            if ($setting->exists && $setting->is_enabled && Branch::query()
                ->where('tenant_id', $tenant->id)
                ->where('default_currency_code', $currency->code)
                ->where('status', 'active')
                ->exists()) {
                throw new InvalidArgumentException('A branch base currency cannot be disabled at tenant level.');
            }

            $setting->tenant_id = $tenant->id;
            $setting->currency_code = $currency->code;
            $setting->is_enabled = ! $setting->exists || ! $setting->is_enabled;
            $setting->is_default = $currency->code === $tenant->default_currency_code || $setting->is_default;

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
