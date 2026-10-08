<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $project_id
 * @property-read string $uploaded_by
 * @property-read string $filename
 * @property-read string $path
 * @property-read array<string, mixed> $state
 */
#[Fillable(['id', 'tenant_id', 'project_id', 'uploaded_by', 'project_estimate_id', 'filename', 'path', 'sha256', 'state'])]
final class BoqImport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<BoqImport>> */
    use HasFactory;
    use HasUuids;

    protected function casts(): array
    {
        return ['state' => 'array'];
    }
}
