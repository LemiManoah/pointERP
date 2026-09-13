<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Workforce\AssignStaffDeployment;
use App\Actions\Workforce\EndStaffDeployment;
use App\Enums\WorkforceTradeCategory;
use App\Http\Requests\Operations\Workforce\StoreStaffDeploymentRequest;
use App\Models\Project;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffDeployment;
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

final class StaffDeploymentController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StaffDeployment::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $branchIds = $branchContext->accessibleBranchIds($actor);
        $canViewAllBranches = $branchContext->canViewAllBranches($actor);

        $projects = Project::query()
            ->with(['branch', 'sites' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->unless($canViewAllBranches, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $project): bool => Gate::forUser($actor)->allows('view', $project))
            ->values();

        return Inertia::render('operations/workforce/index', [
            'tab' => $request->string('tab')->toString() === 'trades' ? 'trades' : 'deployments',
            'trades' => WorkforceTrade::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get()
                ->map(fn (WorkforceTrade $trade): array => [
                    'id' => $trade->id,
                    'code' => $trade->code,
                    'name' => $trade->name,
                    'category' => $trade->category->value,
                    'category_label' => $trade->category->label(),
                    'is_active' => $trade->is_active,
                ]),
            'staff' => Staff::query()
                ->with(['branch', 'primaryTrade'])
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->unless($canViewAllBranches, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
                ->orderBy('name')
                ->get()
                ->map(fn (Staff $staff): array => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'staff_number' => $staff->staff_number,
                    'branch_id' => $staff->branch_id,
                    'branch_name' => $staff->branch->name,
                    'primary_trade_id' => $staff->primary_trade_id,
                    'primary_trade_name' => $staff->primaryTrade?->name,
                ]),
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'reference' => $project->reference,
                'branch_id' => $project->branch_id,
                'branch_name' => $project->branch->name,
                'sites' => $project->sites->map(fn (Site $site): array => [
                    'id' => $site->id,
                    'name' => $site->name,
                ])->values()->all(),
            ]),
            'deployments' => StaffDeployment::query()
                ->with(['assignedBy', 'branch', 'endedBy', 'project', 'site', 'staff', 'trade'])
                ->where('tenant_id', $tenantId)
                ->unless($canViewAllBranches, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
                ->latest('starts_on')
                ->latest('created_at')
                ->get()
                ->map(fn (StaffDeployment $deployment): array => [
                    'id' => $deployment->id,
                    'staff_name' => $deployment->staff->name,
                    'staff_number' => $deployment->staff->staff_number,
                    'project_name' => $deployment->project->name,
                    'project_reference' => $deployment->project->reference,
                    'site_name' => $deployment->site?->name,
                    'branch_name' => $deployment->branch->name,
                    'trade_name' => $deployment->workforce_trade_id === null
                        ? 'Not specified'
                        : $deployment->trade->name,
                    'starts_on' => $deployment->starts_on->toDateString(),
                    'ends_on' => $deployment->ends_on?->toDateString(),
                    'status' => $deployment->status->value,
                    'notes' => $deployment->notes,
                    'assigned_by_name' => $deployment->assignedBy->name,
                    'ended_by_name' => $deployment->endedBy?->name,
                ]),
            'tradeCategories' => collect(WorkforceTradeCategory::cases())->map(fn (WorkforceTradeCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ]),

            'can' => [
                'manageTrades' => Gate::allows('create', WorkforceTrade::class),
                'manageDeployments' => Gate::allows('create', StaffDeployment::class),
            ],
        ]);
    }

    public function store(StoreStaffDeploymentRequest $request, AssignStaffDeployment $action): RedirectResponse
    {
        Gate::authorize('create', StaffDeployment::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $project = Project::query()->findOrFail((string) $request->validated('project_id'));
        Gate::authorize('view', $project);

        /** @var array{staff_id: string, project_id: string, site_id?: string|null, workforce_trade_id?: string|null, starts_on: string, notes?: string|null} $data */
        $data = $request->validated();
        $action->handle($data, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff deployment recorded.']);

        return to_route('workforce.index');
    }

    public function destroy(StaffDeployment $staffDeployment, EndStaffDeployment $action): RedirectResponse
    {
        Gate::authorize('update', $staffDeployment);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($staffDeployment, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff deployment ended.']);

        return to_route('workforce.index');
    }
}
