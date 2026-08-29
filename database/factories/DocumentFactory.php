<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentDiscipline;
use App\Enums\DocumentRevision;
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
            'revision' => DocumentRevision::R0->value,
            'discipline' => DocumentDiscipline::General->value,
            'issuer_id' => null,
            'description' => fake()->sentence(),
            'document_date' => now()->toDateString(),
            'expires_on' => null,
            'confidentiality' => Document::CONFIDENTIALITY_NORMAL,
            'status' => Document::STATUS_ACTIVE,
            'current_version_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
