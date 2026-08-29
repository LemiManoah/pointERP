<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpensePaymentStatus;
use App\Models\ExpensePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpensePayment>
 */
final class ExpensePaymentFactory extends Factory
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
            'expense_id' => null,
            'payment_number' => mb_strtoupper(fake()->unique()->bothify('PAY-####')),
            'paid_at' => now(),
            'amount' => '50000.0000',
            'currency_code' => 'UGX',
            'base_currency_amount' => '50000.0000',
            'exchange_rate' => '1.0000000000',
            'payment_method' => ExpensePaymentMethod::Cash,
            'reference' => null,
            'notes' => null,
            'status' => ExpensePaymentStatus::Recorded,
            'recorded_by' => null,
        ];
    }
}
