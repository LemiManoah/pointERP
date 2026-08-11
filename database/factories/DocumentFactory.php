<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'document_type_id' => DocumentType::factory(),
            'owner_id' => null,
            'title' => fake()->sentence(4),
            'reference' => mb_strtoupper(fake()->unique()->bothify('DOC-####')),
            'document_number' => mb_strtoupper(fake()->unique()->bothify('DC-####')),
            'revision' => '0',
            'discipline' => fake()->randomElement(['Commercial', 'Roadworks', 'HSE', 'Quality']),
            'issuer' => fake()->company(),
            'description' => fake()->sentence(),
            'document_date' => now()->toDateString(),
            'received_on' => now()->toDateString(),
            'expires_on' => null,
            'confidentiality' => Document::CONFIDENTIALITY_NORMAL,
            'status' => Document::STATUS_ACTIVE,
            'current_version_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
