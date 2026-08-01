<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currency = Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'United States Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
        );

        return [
            'name' => fake()->company(),
            'code' => mb_strtoupper(fake()->unique()->bothify('TEN###')),
            'default_currency_code' => $currency->code,
            'is_multibranch' => false,
            'multi_currency_enabled' => false,
            'timezone' => 'Africa/Kampala',
            'status' => 'active',
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }

    public function multibranch(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_multibranch' => true,
        ]);
    }

    public function multiCurrency(): self
    {
        return $this->state(fn (array $attributes): array => [
            'multi_currency_enabled' => true,
        ]);
    }
}
