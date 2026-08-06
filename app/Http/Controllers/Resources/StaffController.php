<?php

declare(strict_types=1);

namespace App\Http\Controllers\Resources;

use App\Actions\Resources\Staff\SaveStaff;
use App\Actions\Resources\Staff\ToggleStaffStatus;
use App\Http\Requests\Resources\Staff\StoreStaffRequest;
use App\Http\Requests\Resources\Staff\UpdateStaffRequest;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffPosition;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class StaffController
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('resources.staff.manage'), 403);

        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $accessibleBranchIds = $branchContext->accessibleBranchIds();
        $canViewAllBranches = $branchContext->canViewAllBranches();

        return Inertia::render('resources/staff/index', [
            'staff' => Staff::query()
                ->with(['branch', 'position', 'user'])
                ->where('tenant_id', $tenantId)
                ->unless($canViewAllBranches, fn ($query) => $query->whereIn('branch_id', $accessibleBranchIds))
                ->orderBy('name')
                ->get()
                ->map(fn (Staff $staff): array => [
                    'id' => $staff->id,
                    'branch_id' => $staff->branch_id,
                    'staff_position_id' => $staff->staff_position_id,
                    'staff_number' => $staff->staff_number,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'phone' => $staff->phone,
                    'status' => $staff->status,
                    'branch_name' => $staff->branch->name,
                    'position_name' => $staff->position->name,
                    'has_user' => $staff->user !== null,
                ]),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->unless($canViewAllBranches, fn ($query) => $query->whereIn('id', $accessibleBranchIds))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ]),
            'positions' => StaffPosition::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (StaffPosition $position): array => [
                    'id' => $position->id,
                    'name' => $position->name,
                ]),
        ]);
    }

    public function store(StoreStaffRequest $request, SaveStaff $action): RedirectResponse
    {
        abort_unless($request->user()?->can('resources.staff.manage'), 403);

        /** @var array{branch_id: string, staff_position_id: string, staff_number: string, name: string, email: string, phone?: string|null, status: string} $data */
        $data = $request->validated();

        $action->handle($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Staff member created.',
        ]);

        return to_route('resources.staff.index');
    }

    public function update(UpdateStaffRequest $request, Staff $staff, SaveStaff $action): RedirectResponse
    {
        abort_unless($request->user()?->can('resources.staff.manage'), 403);
        abort_unless($staff->tenant_id === resolve(TenantContext::class)->id(), 404);
        $branchContext = resolve(BranchContext::class);
        abort_unless($branchContext->canViewAllBranches() || in_array($staff->branch_id, $branchContext->accessibleBranchIds(), true), 404);

        /** @var array{branch_id: string, staff_position_id: string, staff_number: string, name: string, email: string, phone?: string|null, status: string} $data */
        $data = $request->validated();

        $action->handle($data, $staff);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Staff member updated.',
        ]);

        return to_route('resources.staff.index');
    }

    public function destroy(Staff $staff, ToggleStaffStatus $action): RedirectResponse
    {
        abort_unless(auth()->user()?->can('resources.staff.manage'), 403);
        abort_unless($staff->tenant_id === resolve(TenantContext::class)->id(), 404);
        $branchContext = resolve(BranchContext::class);
        abort_unless($branchContext->canViewAllBranches() || in_array($staff->branch_id, $branchContext->accessibleBranchIds(), true), 404);

        $action->handle($staff);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $staff->status === 'active' ? 'Staff member activated.' : 'Staff member deactivated.',
        ]);

        return to_route('resources.staff.index');
    }
}
