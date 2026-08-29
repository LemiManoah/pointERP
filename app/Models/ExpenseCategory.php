<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $code
 * @property-read string $name
 * @property-read string|null $description
 * @property-read bool $requires_evidence
 * @property-read bool $is_active
 */
#[Fillable(['tenant_id', 'code', 'name', 'description', 'requires_evidence', 'is_active', 'created_by', 'updated_by'])]
final class ExpenseCategory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ExpenseCategory>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return HasMany<ExpenseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }
}
