<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
