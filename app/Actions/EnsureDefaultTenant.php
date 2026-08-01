<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final readonly class EnsureDefaultTenant
{
    public function handle(): Tenant
    {
        return DB::transaction(function (): Tenant {
            Currency::query()->updateOrCreate(
                ['code' => 'USD'],
                ['name' => 'United States Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
            );

            Currency::query()->updateOrCreate(
                ['code' => 'UGX'],
                ['name' => 'Ugandan Shilling', 'symbol' => 'UGX', 'decimal_places' => 0, 'is_active' => true],
            );

            Currency::query()->updateOrCreate(
                ['code' => 'SSP'],
                ['name' => 'South Sudanese Pound', 'symbol' => 'SSP', 'decimal_places' => 2, 'is_active' => true],
            );

            Currency::query()->updateOrCreate(
                ['code' => 'CDF'],
                ['name' => 'Congolese Franc', 'symbol' => 'CDF', 'decimal_places' => 2, 'is_active' => true],
            );

            Country::query()->updateOrCreate(
                ['code' => 'UG'],
                ['name' => 'Uganda', 'iso3_code' => 'UGA', 'default_currency_code' => 'UGX', 'is_active' => true],
            );

            Country::query()->updateOrCreate(
                ['code' => 'SS'],
                ['name' => 'South Sudan', 'iso3_code' => 'SSD', 'default_currency_code' => 'SSP', 'is_active' => true],
            );

            Country::query()->updateOrCreate(
                ['code' => 'CD'],
                ['name' => 'DRC', 'iso3_code' => 'COD', 'default_currency_code' => 'CDF', 'is_active' => true],
            );

            return Tenant::query()->updateOrCreate(
                ['code' => 'POINT'],
                [
                    'name' => 'Point Investment Co. Ltd',
                    'default_currency_code' => 'USD',
                    'is_multibranch' => true,
                    'multi_currency_enabled' => true,
                    'timezone' => 'Africa/Kampala',
                    'status' => 'active',
                ],
            );
        });
    }
}
