<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstimateResourceType;
use App\Models\EstimateResourceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EstimateResourceLine> */
final class EstimateResourceLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_estimate_line_id' => null,
            'inventory_item_id' => null,
            'unit_of_measure_id' => null,
            'resource_type' => EstimateResourceType::Other,
            'name' => fake()->words(2, true),
            'quantity_per_work_unit' => fake()->randomFloat(6, 0.01, 10),
            'estimated_unit_cost' => null,
            'notes' => null,
            'sort_order' => 0,
        ];
    }
}
