<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProjectActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActivity>
 */
final class ProjectActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'project_id' => null,
            'site_id' => null,
            'code' => mb_strtoupper(fake()->unique()->bothify('ACT-###')),
            'boq_item_number' => null,
            'name' => fake()->sentence(4),
            'unit' => 'm3',
            'planned_quantity' => fake()->numberBetween(10, 1000),
            'approved_quantity' => 0,
            'rate_amount' => null,
            'currency_code' => 'UGX',
            'status' => 'active',
            'sort_order' => 0,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
