<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
final class DocumentTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'name' => fake()->unique()->words(2, true),
            'code' => mb_strtoupper(fake()->unique()->bothify('DOC-###')),
            'description' => fake()->sentence(),
            'requires_expiry_date' => false,
            'is_confidential' => false,
            'is_system' => false,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
