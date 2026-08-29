<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosReturnStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'branch_id', 'pos_sale_id', 'return_number', 'status', 'reason', 'refund_amount', 'created_by', 'approved_by', 'approved_at'])]
final class PosReturn extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<PosReturn>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['status' => PosReturnStatus::class, 'refund_amount' => 'decimal:4', 'approved_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<PosSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    /** @return HasMany<PosReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PosReturnLine::class);
    }
}
