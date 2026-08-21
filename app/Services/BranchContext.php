<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Validation\ValidationException;

final readonly class BranchContext
{
    private const string SESSION_BRANCH_ID = 'current_branch_id';

    private const string SESSION_ALL_BRANCHES = 'current_branch_all';

    public function __construct(
        private AuthFactory $auth,
        private SessionManager $session,
        private TenantContext $tenantContext,
    ) {
        //
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(?User $user = null): Collection
    {
        $user ??= $this->user();

        if (! $user instanceof User) {
            return new Collection;
        }

        $query = Branch::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('status', 'active')
            ->orderBy('name');

        $singleTenantBranch = $this->singleTenantBranch($user);

        if ($singleTenantBranch instanceof Branch) {
            return new Collection([$singleTenantBranch]);
        }

        if ($this->canViewAllBranches($user)) {
            return $query->get();
        }

        return $query
            ->whereHas('users', fn (Builder $query) => $query->whereKey($user->id))
            ->get();
    }

    /**
     * @return list<string>
     */
    public function accessibleBranchIds(?User $user = null): array
    {
        return $this->accessibleBranches($user)
            ->pluck('id')
            ->values()
            ->all();
    }

    public function current(?User $user = null): ?Branch
    {
        $user ??= $this->user();

        if (! $user instanceof User) {
            return null;
        }

        if (
            $this->tenantContext->current()->is_multibranch
            && $this->session->get(self::SESSION_ALL_BRANCHES) === true
            && $this->canViewAllBranches($user)
        ) {
            return null;
        }

        $accessibleBranches = $this->accessibleBranches($user);
        $selectedBranchId = $this->session->get(self::SESSION_BRANCH_ID);

        if (is_string($selectedBranchId)) {
            $selectedBranch = $accessibleBranches->firstWhere('id', $selectedBranchId);

            if ($selectedBranch instanceof Branch) {
                return $selectedBranch;
            }
        }

        $defaultBranch = $this->defaultBranch($user, $accessibleBranches);

        if ($defaultBranch instanceof Branch) {
            $this->selectBranch($defaultBranch);

            return $defaultBranch;
        }

        if ($this->canViewAllBranches($user)) {
            $this->selectAllBranches($user);

            return null;
        }

        $this->clear();

        return null;
    }

    public function canViewAllBranches(?User $user = null): bool
    {
        $user ??= $this->user();

        return $user instanceof User && $user->can('branches.view-all');
    }

    public function operationalDefault(?User $user = null): ?Branch
    {
        $user ??= $this->user();

        return $user instanceof User ? $this->defaultBranch($user) : null;
    }

    public function select(?string $branchId, ?User $user = null): ?Branch
    {
        $user ??= $this->user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'branch_id' => 'You must be signed in to select a branch.',
            ]);
        }

        if ($branchId === null || $branchId === '') {
            $singleTenantBranch = $this->singleTenantBranch($user);

            if ($singleTenantBranch instanceof Branch) {
                $this->selectBranch($singleTenantBranch);

                return $singleTenantBranch;
            }

            return $this->selectAllBranches($user);
        }

        $branch = $this->accessibleBranches($user)->firstWhere('id', $branchId);

        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'You do not have access to that branch.',
            ]);
        }

        $this->selectBranch($branch);

        return $branch;
    }

    public function clear(): void
    {
        $this->session->forget([self::SESSION_BRANCH_ID, self::SESSION_ALL_BRANCHES]);
    }

    private function singleTenantBranch(User $user): ?Branch
    {
        $tenant = $this->tenantContext->current();

        if ($tenant->is_multibranch) {
            return null;
        }

        $branches = Branch::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(2)
            ->get();

        if ($branches->count() !== 1) {
            return null;
        }

        $branch = $branches->first();

        if (! $branch instanceof Branch) {
            return null;
        }

        $hasDefaultBranch = $user->branches()
            ->whereKey($branch->id)
            ->wherePivot('is_default', true)
            ->exists();

        if (! $hasDefaultBranch) {
            $user->branches()->syncWithoutDetaching([
                $branch->id => ['is_default' => true],
            ]);
            $user->branches()->updateExistingPivot($branch->id, ['is_default' => true]);
        }

        return $branch;
    }

    /**
     * @param  Collection<int, Branch>|null  $accessibleBranches
     */
    private function defaultBranch(User $user, ?Collection $accessibleBranches = null): ?Branch
    {
        $accessibleBranches ??= $this->accessibleBranches($user);

        $defaultBranchId = $user->branches()
            ->wherePivot('is_default', true)
            ->value('branches.id');

        if (is_string($defaultBranchId)) {
            $defaultBranch = $accessibleBranches->firstWhere('id', $defaultBranchId);

            if ($defaultBranch instanceof Branch) {
                return $defaultBranch;
            }
        }

        return $accessibleBranches->first();
    }

    private function selectAllBranches(User $user): null
    {
        if (! $this->canViewAllBranches($user)) {
            throw ValidationException::withMessages([
                'branch_id' => 'You do not have permission to select all branches.',
            ]);
        }

        $this->session->put(self::SESSION_ALL_BRANCHES, true);
        $this->session->forget(self::SESSION_BRANCH_ID);

        return null;
    }

    private function selectBranch(Branch $branch): void
    {
        $this->session->put(self::SESSION_BRANCH_ID, $branch->id);
        $this->session->put(self::SESSION_ALL_BRANCHES, false);
    }

    private function user(): ?User
    {
        $user = $this->auth->guard()->user();

        return $user instanceof User ? $user : null;
    }
}
