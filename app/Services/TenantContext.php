<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function __construct(private readonly AuthFactory $auth)
    {
        //
    }

    public function set(Tenant $tenant): void
    {
        if ($tenant->status !== 'active') {
            throw MissingTenantContextException::inactive();
        }

        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function has(): bool
    {
        return $this->tenant instanceof Tenant || $this->resolveFromUser() instanceof Tenant;
    }

    public function current(): Tenant
    {
        $tenant = $this->tenant ?? $this->resolveFromUser();

        if (! $tenant instanceof Tenant) {
            throw MissingTenantContextException::unresolved();
        }

        if ($tenant->status !== 'active') {
            throw MissingTenantContextException::inactive();
        }

        return $this->tenant = $tenant;
    }

    public function id(): string
    {
        return $this->current()->id;
    }

    private function resolveFromUser(): ?Tenant
    {
        $user = $this->auth->guard()->user();

        if (! $user instanceof User) {
            return null;
        }

        return Tenant::query()
            ->whereKey($user->tenant_id)
            ->active()
            ->first();
    }
}
