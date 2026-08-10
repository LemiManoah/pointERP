<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'type' => Customer::TYPE_CLIENT,
            'name' => fake()->company(),
            'code' => mb_strtoupper(fake()->unique()->lexify('????')),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'tax_number' => null,
            'address' => fake()->address(),
            'status' => 'active',
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function subcontractor(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Customer::TYPE_SUBCONTRACTOR,
        ]);
    }
}
