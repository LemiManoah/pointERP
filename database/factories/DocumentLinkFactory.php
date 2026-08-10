<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentLink>
 */
final class DocumentLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'linkable_type' => Project::class,
            'linkable_id' => Project::factory(),
            'created_by' => null,
        ];
    }
}
