<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseLine>
 */
final class ExpenseLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => null,
            'expense_item_id' => null,
            'project_id' => null,
            'site_id' => null,
            'project_activity_id' => null,
            'expense_category_name_snapshot' => 'Administration',
            'expense_item_name_snapshot' => fake()->words(2, true),
            'description' => null,
            'quantity' => '1.0000',
            'unit_amount' => '100000.0000',
            'amount' => '100000.0000',
            'base_currency_amount' => '100000.0000',
            'sort_order' => 0,
        ];
    }
}
