<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'project_id', 'work_item_key'])]
final class ProjectBoqItem extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ProjectBoqItem>> */
    use HasFactory;

    use HasUuids;
}
