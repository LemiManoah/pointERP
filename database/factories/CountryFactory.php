<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
final class CountryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currency = Currency::query()->first()
            ?? Currency::factory()->usd()->create();

        return [
            'code' => mb_strtoupper(fake()->unique()->lexify('??')),
            'name' => fake()->country(),
            'iso3_code' => mb_strtoupper(fake()->unique()->lexify('???')),
            'default_currency_code' => $currency->code,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
