<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = $user instanceof User ? $user->tenant : null;
        $branchContext = $user instanceof User ? resolve(BranchContext::class) : null;
        $currentBranch = $branchContext?->current($user instanceof User ? $user : null);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? [
                    ...$user->toArray(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    'roles' => $user->getRoleNames()->values()->all(),
                ] : null,
            ],
            'currentTenant' => $tenant?->only([
                'id',
                'name',
                'code',
                'default_currency_code',
                'is_multibranch',
                'multi_currency_enabled',
                'timezone',
                'status',
            ]),
            'currentBranch' => $currentBranch?->only([
                'id',
                'name',
                'code',
                'country_code',
                'default_currency_code',
                'status',
            ]),
            'accessibleBranches' => $branchContext?->accessibleBranches($user instanceof User ? $user : null)
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'country_code' => $branch->country_code,
                    'default_currency_code' => $branch->default_currency_code,
                    'status' => $branch->status,
                ])
                ->values()
                ->all() ?? [],
            'canViewAllBranches' => $branchContext?->canViewAllBranches($user instanceof User ? $user : null) ?? false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
