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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $expense_category_id
 * @property-read string|null $default_unit_of_measure_id
 * @property-read string $code
 * @property-read string $name
 * @property-read string|null $description
 * @property-read bool $has_quantity
 * @property-read bool $requires_evidence
 * @property-read bool $is_active
 * @property-read ExpenseCategory $category
 * @property-read UnitOfMeasure|null $defaultUnit
 */
#[Fillable(['tenant_id', 'expense_category_id', 'default_unit_of_measure_id', 'code', 'name', 'description', 'has_quantity', 'requires_evidence', 'is_active', 'created_by', 'updated_by'])]
final class ExpenseItem extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ExpenseItem>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'has_quantity' => 'boolean',
            'requires_evidence' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'default_unit_of_measure_id');
    }

    /** @return HasMany<ExpenseLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class);
    }
}
