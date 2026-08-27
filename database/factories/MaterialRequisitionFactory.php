<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MaterialRequisitionPriority;
use App\Enums\MaterialRequisitionStatus;
use App\Models\MaterialRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialRequisition>
 */
final class MaterialRequisitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => mb_strtoupper(fake()->unique()->bothify('MR-####-????')),
            'department' => fake()->optional()->words(2, true),
            'required_by_date' => now()->addWeek()->toDateString(),
            'priority' => MaterialRequisitionPriority::Normal,
            'status' => MaterialRequisitionStatus::Draft,
            'reason' => fake()->sentence(),
        ];
    }
}
