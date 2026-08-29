<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosReturnDisposition;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'pos_return_id', 'pos_sale_line_id', 'inventory_batch_id', 'inventory_stock_movement_id', 'quantity', 'stock_quantity', 'refund_amount', 'disposition'])]
final class PosReturnLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<PosReturnLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['quantity' => 'decimal:4', 'stock_quantity' => 'decimal:4', 'refund_amount' => 'decimal:4', 'disposition' => PosReturnDisposition::class, 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<PosReturn, $this> */
    public function posReturn(): BelongsTo
    {
        return $this->belongsTo(PosReturn::class);
    }

    /** @return BelongsTo<PosSaleLine, $this> */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(PosSaleLine::class, 'pos_sale_line_id');
    }
}
