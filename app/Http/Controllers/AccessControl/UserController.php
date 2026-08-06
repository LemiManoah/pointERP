<?php

declare(strict_types=1);

namespace App\Http\Controllers\AccessControl;

use App\Actions\AccessControl\Users\CreateAccessUser;
use App\Actions\AccessControl\Users\ToggleUserStatus;
use App\Actions\AccessControl\Users\UpdateAccessUser;
use App\Http\Requests\AccessControl\Users\StoreUserRequest;
use App\Http\Requests\AccessControl\Users\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class UserController
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('access-control.users.manage'), 403);

        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $accessibleBranchIds = $branchContext->accessibleBranchIds();
        $canViewAllBranches = $branchContext->canViewAllBranches();

        return Inertia::render('access-control/users/index', [
            'users' => User::query()
                ->with(['branches', 'roles', 'permissions', 'staff.branch', 'staff.position'])
                ->where('tenant_id', $tenantId)
                ->when(
                    ! $canViewAllBranches,
                    fn ($query) => $query->whereHas('branches', fn ($query) => $query->whereIn('branches.id', $accessibleBranchIds)),
                )
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'staff_id' => $user->staff_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'staff_number' => $user->staff?->staff_number,
                    'branch_name' => $user->staff?->branch?->name,
                    'position_name' => $user->staff?->position?->name,
                    'is_active' => $user->is_active,
                    'is_director' => $user->is_director,
                    'last_login_at' => $user->last_login_at?->toDateTimeString(),
                    'branch_ids' => $user->branches->pluck('id')->values()->all(),
                    'default_branch_id' => $user->branches()
                        ->wherePivot('is_default', true)
                        ->value('branches.id'),
                    'roles' => $user->getRoleNames()->all(),
                    'permissions' => $user->getDirectPermissions()->pluck('name')->values()->all(),
                ]),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->when(! $canViewAllBranches, fn ($query) => $query->whereIn('id', $accessibleBranchIds))
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ])
                ->values()
                ->all(),
            'roles' => Role::query()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all(),
            'permissions' => Permission::query()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all(),
            'staff' => Staff::query()
                ->with(['branch', 'position', 'user'])
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->when(! $canViewAllBranches, fn ($query) => $query->whereIn('branch_id', $accessibleBranchIds))
                ->orderBy('name')
                ->get()
                ->map(fn (Staff $staff): array => [
                    'id' => $staff->id,
                    'staff_number' => $staff->staff_number,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'branch_id' => $staff->branch_id,
                    'branch_name' => $staff->branch->name,
                    'position_name' => $staff->position->name,
                    'user_id' => $staff->user?->id,
                ]),
        ]);
    }

    public function store(StoreUserRequest $request, CreateAccessUser $action): RedirectResponse
    {
        abort_unless($request->user()?->can('access-control.users.manage'), 403);

        /** @var array{staff_id: string, password: string, is_active?: bool, is_director?: bool, roles?: list<string>, permissions?: list<string>, branch_ids?: list<string>, default_branch_id?: string|null} $data */
        $data = $request->validated();

        $action->handle($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User created.',
        ]);

        return to_route('access-control.users.index');
    }

    public function update(UpdateUserRequest $request, User $user, UpdateAccessUser $action): RedirectResponse
    {
        abort_unless($request->user()?->can('access-control.users.manage'), 403);
        abort_unless($user->tenant_id === resolve(TenantContext::class)->id(), 404);
        $branchContext = resolve(BranchContext::class);
        abort_unless(
            $branchContext->canViewAllBranches() || $user->branches()->whereIn('branches.id', $branchContext->accessibleBranchIds())->exists(),
            404,
        );

        /** @var array{staff_id: string, password?: string|null, is_active?: bool, is_director?: bool, roles?: list<string>, permissions?: list<string>, branch_ids?: list<string>, default_branch_id?: string|null} $data */
        $data = $request->validated();

        $action->handle($user, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User updated.',
        ]);

        return to_route('access-control.users.index');
    }

    public function destroy(User $user, ToggleUserStatus $action): RedirectResponse
    {
        abort_unless(auth()->user()?->can('access-control.users.manage'), 403);
        abort_unless($user->tenant_id === resolve(TenantContext::class)->id(), 404);
        $branchContext = resolve(BranchContext::class);
        abort_unless(
            $branchContext->canViewAllBranches() || $user->branches()->whereIn('branches.id', $branchContext->accessibleBranchIds())->exists(),
            404,
        );

        $action->handle($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active ? 'User activated.' : 'User deactivated.',
        ]);

        return to_route('access-control.users.index');
    }
}
