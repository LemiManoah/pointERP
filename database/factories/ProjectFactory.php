<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'customer_id' => null,
            'contract_id' => null,
            'reference' => mb_strtoupper(fake()->unique()->bothify('PRJ-####')),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'manager_id' => null,
            'base_currency_code' => 'UGX',
            'budget_amount' => fake()->numberBetween(1000000, 100000000),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'reporting_deadline' => '18:00',
            'status' => 'active',
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
