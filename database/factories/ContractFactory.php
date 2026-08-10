<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
final class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'customer_id' => Customer::factory(),
            'reference' => mb_strtoupper(fake()->unique()->bothify('CON-####')),
            'title' => fake()->sentence(4),
            'scope_summary' => fake()->sentence(),
            'contract_value' => fake()->numberBetween(1000000, 100000000),
            'currency_code' => 'UGX',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'retention_percent' => '10.0000',
            'payment_terms' => null,
            'status' => 'active',
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
