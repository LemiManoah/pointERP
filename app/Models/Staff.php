<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $staff_position_id
 * @property-read string $staff_number
 * @property-read string $name
 * @property-read string $email
 * @property-read string|null $phone
 * @property-read string $status
 * @property-read Branch $branch
 * @property-read StaffPosition $position
 * @property-read User|null $user
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'staff_position_id',
    'staff_number',
    'name',
    'email',
    'phone',
    'status',
])]
#[Table(name: 'staff')]
final class Staff extends Model
{
    /** @use HasFactory<Factory<Staff>> */
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
            'branch_id' => 'string',
            'staff_position_id' => 'string',
            'staff_number' => 'string',
            'name' => 'string',
            'email' => 'string',
            'phone' => 'string',
            'status' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<StaffPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(StaffPosition::class, 'staff_position_id');
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
