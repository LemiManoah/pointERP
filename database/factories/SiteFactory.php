<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
final class SiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'project_id' => null,
            'reference' => mb_strtoupper(fake()->unique()->bothify('SITE-##')),
            'name' => fake()->city().' Site',
            'location_name' => fake()->streetAddress(),
            'latitude' => null,
            'longitude' => null,
            'manager_id' => null,
            'reporting_deadline' => '18:00',
            'status' => 'active',
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
