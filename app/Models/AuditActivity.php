<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity;

/**
 * @property-read int $id
 * @property-read string|null $tenant_id
 * @property-read string|null $branch_id
 * @property-read string|null $reason
 * @property-read string|null $ip_address
 * @property-read string|null $user_agent
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Branch|null $branch
 */
final class AuditActivity extends Activity
{
    use HasFactory;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'tenant_id' => 'string',
            'branch_id' => 'string',
            'reason' => 'string',
            'ip_address' => 'string',
            'user_agent' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
