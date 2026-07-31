<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $guard_name
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Permission extends SpatiePermission
{
    use HasUuids;
}
