<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosPaymentMethod;
use App\Enums\PosPaymentStatus;
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
 * @property-read string $payment_number
 * @property-read PosPaymentMethod $method
 * @property-read PosPaymentStatus $status
 * @property-read string $amount
 * @property-read string|null $reference
 * @property-read CarbonInterface $recorded_at
 */
#[Fillable(['tenant_id', 'branch_id', 'pos_sale_id', 'payment_number', 'method', 'amount', 'currency_code', 'reference', 'notes', 'status', 'recorded_by', 'recorded_at', 'reversal_of_id', 'reversed_by', 'reversed_at', 'reversal_reason'])]
final class PosPayment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<PosPayment>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['method' => PosPaymentMethod::class, 'status' => PosPaymentStatus::class, 'amount' => 'decimal:4', 'recorded_at' => 'datetime', 'reversed_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<PosSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
