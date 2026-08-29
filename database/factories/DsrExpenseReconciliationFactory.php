<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DsrExpenseReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DsrExpenseReconciliation>
 */
final class DsrExpenseReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_site_report_cost_line_id' => null,
            'expense_line_id' => null,
            'reconciled_by' => null,
            'reconciled_at' => now(),
            'reason' => fake()->sentence(),
        ];
    }
}
