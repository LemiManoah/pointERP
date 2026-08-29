<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProjectEstimateLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectEstimateLine> */
final class ProjectEstimateLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_estimate_id' => null,
            'site_id' => null,
            'unit_of_measure_id' => null,
            'work_item_key' => fake()->uuid(),
            'boq_reference' => null,
            'code' => mb_strtoupper(fake()->bothify('WORK-###')),
            'name' => fake()->sentence(3),
            'planned_quantity' => fake()->randomFloat(4, 1, 10000),
            'selling_rate' => fake()->randomFloat(4, 1000, 100000),
            'estimated_unit_cost' => fake()->randomFloat(4, 500, 80000),
            'sort_order' => 0,
            'notes' => null,
        ];
    }
}
