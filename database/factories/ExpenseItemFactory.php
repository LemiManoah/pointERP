<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseItem>
 */
final class ExpenseItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => null,
            'default_unit_of_measure_id' => null,
            'code' => mb_strtoupper(fake()->unique()->bothify('ITEM-###')),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'requires_evidence' => false,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
