<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
final class TenantOwnedWidget extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasFactory;

    protected $guarded = [];
}
