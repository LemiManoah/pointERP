<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkforceTradeCategory;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read WorkforceTradeCategory $category
 */
#[Fillable(['tenant_id', 'code', 'name', 'category', 'is_active', 'created_by', 'updated_by'])]
final class WorkforceTrade extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<WorkforceTrade>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'category' => WorkforceTradeCategory::class,
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return HasMany<Staff, $this> */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'primary_trade_id');
    }

    /** @return HasMany<StaffDeployment, $this> */
    public function deployments(): HasMany
    {
        return $this->hasMany(StaffDeployment::class);
    }
}
