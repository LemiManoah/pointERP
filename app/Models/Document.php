<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'branch_id',
    'document_type_id',
    'owner_id',
    'title',
    'reference',
    'document_number',
    'revision',
    'discipline',
    'issuer',
    'description',
    'document_date',
    'received_on',
    'expires_on',
    'confidentiality',
    'status',
    'current_version_id',
    'created_by',
    'updated_by',
])]
final class Document extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<Document>> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_SUPERSEDED = 'superseded';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_ARCHIVED = 'archived';

    public const string CONFIDENTIALITY_NORMAL = 'normal';

    public const string CONFIDENTIALITY_RESTRICTED = 'restricted';

    public const string CONFIDENTIALITY_CONFIDENTIAL = 'confidential';

    public const string CONFIDENTIALITY_COMMERCIAL = 'commercial';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'branch_id' => 'string',
            'document_type_id' => 'string',
            'owner_id' => 'string',
            'document_date' => 'date',
            'received_on' => 'date',
            'expires_on' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * @return HasMany<DocumentLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class);
    }

    public function isConfidential(): bool
    {
        return in_array($this->confidentiality, [
            self::CONFIDENTIALITY_RESTRICTED,
            self::CONFIDENTIALITY_CONFIDENTIAL,
            self::CONFIDENTIALITY_COMMERCIAL,
        ], true) || $this->type?->is_confidential === true;
    }

    public function isExpired(): bool
    {
        return $this->expires_on instanceof CarbonInterface && $this->expires_on->isPast();
    }

    public function isDrawing(): bool
    {
        return in_array($this->type?->code, ['DRAWING', 'REVISED_DRAWING'], true);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    protected function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    protected function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }
}
