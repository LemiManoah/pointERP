<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'name',
    'code',
    'description',
    'requires_expiry_date',
    'is_confidential',
    'is_system',
    'is_active',
    'created_by',
    'updated_by',
])]
final class DocumentType extends Model
{
    /** @use HasFactory<Factory<DocumentType>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'requires_expiry_date' => 'boolean',
            'is_confidential' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @param  Builder<DocumentType>  $query
     * @return Builder<DocumentType>
     */
    protected function scopeAvailableToTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereNull('tenant_id')
            ->orWhere('tenant_id', $tenantId));
    }
}
