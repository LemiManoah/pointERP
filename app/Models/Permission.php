<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $guard_name
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 *
 * @method static PermissionFactory factory($count = null, $state = [])
 */
final class Permission extends SpatiePermission
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    use HasUuids;
}
