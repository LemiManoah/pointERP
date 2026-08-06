<?php

declare(strict_types=1);

namespace App\Http\Controllers\AccessControl;

use App\Actions\AccessControl\Roles\CreateRole;
use App\Actions\AccessControl\Roles\UpdateRole;
use App\Http\Requests\AccessControl\Roles\StoreRoleRequest;
use App\Http\Requests\AccessControl\Roles\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('access-control.roles.manage'), 403);

        return Inertia::render('access-control/roles/index', [
            'roles' => Role::query()
                ->with('permissions')
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'users_count' => (int) $role->getAttribute('users_count'),
                    'permissions' => $role->permissions->pluck('name')->values()->all(),
                ]),
            'permissions' => Permission::query()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreRoleRequest $request, CreateRole $action): RedirectResponse
    {
        abort_unless($request->user()?->can('access-control.roles.manage'), 403);

        /** @var array{name: string, permissions?: list<string>} $data */
        $data = $request->validated();

        $action->handle($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role created.',
        ]);

        return to_route('access-control.roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $action): RedirectResponse
    {
        abort_unless($request->user()?->can('access-control.roles.manage'), 403);

        /** @var array{name: string, permissions?: list<string>} $data */
        $data = $request->validated();

        $action->handle($role, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role updated.',
        ]);

        return to_route('access-control.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless(auth()->user()?->can('access-control.roles.manage'), 403);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'This role is assigned to users and cannot be deleted.',
            ]);
        }

        $role->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role deleted.',
        ]);

        return to_route('access-control.roles.index');
    }
}
