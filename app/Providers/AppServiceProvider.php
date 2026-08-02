<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Scopes\TenantScope;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(BranchContext::class);
        $this->app->scoped(TenantScope::class);
    }
}
