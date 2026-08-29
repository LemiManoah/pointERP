<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExpensePayeeType;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
final class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'expense_number' => mb_strtoupper(fake()->unique()->bothify('EXP-####')),
            'expense_date' => now()->toDateString(),
            'payee_type' => ExpensePayeeType::Other,
            'customer_id' => null,
            'staff_id' => null,
            'payee_name_snapshot' => fake()->company(),
            'currency_code' => 'UGX',
            'base_currency_code' => 'UGX',
            'exchange_rate_id' => null,
            'exchange_rate' => '1.0000000000',
            'subtotal' => '100000.0000',
            'total_amount' => '100000.0000',
            'base_currency_total' => '100000.0000',
            'description' => fake()->sentence(),
            'reference' => null,
            'status' => ExpenseStatus::Draft,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
