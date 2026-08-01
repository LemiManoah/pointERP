<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $guard_name
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 *
 * @method static RoleFactory factory($count = null, $state = [])
 */
final class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    use HasUuids;
}
