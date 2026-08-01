<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\TenantMismatchException;
use App\Models\Scopes\TenantScope;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;

// @phpstan-ignore trait.unused
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(resolve(TenantScope::class));

        static::creating(function (Model $model): void {
            $tenantId = resolve(TenantContext::class)->id();

            if ($model->getAttribute('tenant_id') !== null && $model->getAttribute('tenant_id') !== $tenantId) {
                throw TenantMismatchException::forCreation();
            }

            $model->setAttribute('tenant_id', $tenantId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw TenantMismatchException::immutable();
            }
        });
    }
}
