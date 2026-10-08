<?php

declare(strict_types=1);

namespace App\Http\Controllers\Resources;

use App\Actions\Resources\Staff\SaveStaff;
use App\Actions\Resources\Staff\ToggleStaffStatus;
use App\Enums\StaffEmploymentType;
use App\Http\Requests\Resources\Staff\StoreStaffRequest;
use App\Http\Requests\Resources\Staff\UpdateStaffRequest;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class StaffController
{
    public function index(Request $request): Response
    {
        $isWorker = $request->routeIs('workforce.workers.*');
        Gate::authorize($isWorker ? 'viewWorkforce' : 'viewAny', Staff::class);

        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $accessibleBranchIds = $branchContext->accessibleBranchIds();
        $canViewAllBranches = $branchContext->canViewAllBranches();

        return Inertia::render('resources/staff/index', [
            'directory' => $isWorker ? 'workforce' : 'company_staff',
            'can' => [
                'companyStaff' => Gate::allows('viewAny', Staff::class),
                'workers' => Gate::allows('viewWorkforce', Staff::class),
                'reclassify' => Gate::allows('viewAny', Staff::class) && Gate::allows('viewWorkforce', Staff::class),
            ],
            'staff' => Staff::query()
                ->with(['branch', 'position', 'primaryTrade', 'user'])
                ->where('tenant_id', $tenantId)
                ->where('person_category', $isWorker ? 'workforce' : 'company_staff')
                ->unless($canViewAllBranches, fn (Builder $query) => $query->whereIn('branch_id', $accessibleBranchIds))
                ->orderBy('name')
                ->get()
                ->map(fn (Staff $staff): array => [
                    'id' => $staff->id,
                    'person_category' => $staff->person_category,
                    'branch_id' => $staff->branch_id,
                    'staff_position_id' => $staff->staff_position_id,
                    'employment_type' => $staff->employment_type->value,
                    'employment_type_label' => $staff->employment_type->label(),
                    'primary_trade_id' => $staff->primary_trade_id,
                    'primary_trade_name' => $staff->primaryTrade?->name,
                    'staff_number' => $staff->staff_number,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'phone' => $staff->phone,
                    'status' => $staff->status,
                    'branch_name' => $staff->branch->name,
                    'position_name' => $staff->position?->name,
                    'has_user' => $staff->user !== null,
                ]),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->unless($canViewAllBranches, fn (Builder $query) => $query->whereIn('id', $accessibleBranchIds))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name]),
            'positions' => StaffPosition::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (StaffPosition $position): array => ['id' => $position->id, 'name' => $position->name]),
            'trades' => WorkforceTrade::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (WorkforceTrade $trade): array => ['id' => $trade->id, 'name' => $trade->name]),
            'employmentTypes' => collect(StaffEmploymentType::cases())
                ->map(fn (StaffEmploymentType $type): array => ['id' => $type->value, 'name' => $type->label()]),
        ]);
    }

    public function store(StoreStaffRequest $request, SaveStaff $action): RedirectResponse
    {
        $isWorker = $request->routeIs('workforce.workers.*');
        Gate::authorize($isWorker ? 'createWorker' : 'create', Staff::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id: string, staff_position_id?: string|null, person_category?: string, employment_type: string, primary_trade_id?: string|null, staff_number?: string|null, name: string, email?: string|null, phone?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle([...$data, 'person_category' => $isWorker ? 'workforce' : 'company_staff'], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => $isWorker ? 'Worker registered. You can now assign a deployment.' : 'Staff member created.']);

        return to_route($isWorker ? 'workforce.workers.index' : 'resources.staff.index');
    }

    public function update(UpdateStaffRequest $request, Staff $staff, SaveStaff $action): RedirectResponse
    {
        $isWorker = $request->routeIs('workforce.workers.*');
        abort_unless($staff->person_category === ($isWorker ? 'workforce' : 'company_staff'), 404);
        Gate::authorize('update', $staff);
        if ($request->validated('person_category', $staff->person_category) !== $staff->person_category) {
            Gate::authorize($request->validated('person_category') === 'workforce' ? 'createWorker' : 'create', Staff::class);
        }
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id: string, staff_position_id?: string|null, person_category?: string, employment_type: string, primary_trade_id?: string|null, staff_number?: string|null, name: string, email?: string|null, phone?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $staff);
        $isWorker = $staff->person_category === 'workforce';

        Inertia::flash('toast', ['type' => 'success', 'message' => $isWorker ? 'Worker updated.' : 'Staff member updated.']);

        return to_route($isWorker ? 'workforce.workers.index' : 'resources.staff.index');
    }

    public function destroy(Request $request, Staff $staff, ToggleStaffStatus $action): RedirectResponse
    {
        $isWorker = $request->routeIs('workforce.workers.*');
        abort_unless($staff->person_category === ($isWorker ? 'workforce' : 'company_staff'), 404);
        Gate::authorize('delete', $staff);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($staff, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $staff->status === 'active' ? 'Staff member activated.' : 'Staff member deactivated.',
        ]);

        return to_route($isWorker ? 'workforce.workers.index' : 'resources.staff.index');
    }
}
