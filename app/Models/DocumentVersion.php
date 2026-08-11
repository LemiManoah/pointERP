<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $document_id
 * @property-read int $version_number
 * @property-read string $disk
 * @property-read string $path
 * @property-read string $original_name
 * @property-read string|null $mime_type
 * @property-read int $size_bytes
 * @property-read string|null $checksum
 * @property-read string|null $notes
 * @property-read string|null $uploaded_by
 * @property-read CarbonInterface|null $uploaded_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Document|null $document
 * @property-read User|null $uploadedBy
 */
#[Fillable([
    'tenant_id',
    'document_id',
    'version_number',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'checksum',
    'notes',
    'uploaded_by',
    'uploaded_at',
])]
final class DocumentVersion extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<DocumentVersion>> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'document_id' => 'string',
            'version_number' => 'integer',
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
