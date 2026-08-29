<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
final class ExpenseCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('EXP-###')),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'requires_evidence' => false,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
