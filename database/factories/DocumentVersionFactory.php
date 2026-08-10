<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVersion>
 */
final class DocumentVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version_number' => 1,
            'disk' => 'local',
            'path' => fake()->filePath(),
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1000, 5000000),
            'checksum' => hash('sha256', fake()->uuid()),
            'notes' => null,
            'uploaded_by' => null,
            'uploaded_at' => now(),
        ];
    }
}
