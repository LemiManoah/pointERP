<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectEstimateStatus;
use App\Models\ProjectEstimate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectEstimate> */
final class ProjectEstimateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'project_id' => null,
            'version_number' => 1,
            'title' => fake()->sentence(4),
            'currency_code' => 'UGX',
            'status' => ProjectEstimateStatus::Draft,
            'is_baseline' => false,
            'notes' => null,
            'approved_by' => null,
            'approved_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
