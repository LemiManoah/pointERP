<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Projects\SaveProject;
use App\Actions\Workforce\EndProjectDeployments;
use App\Enums\ProjectType;
use App\Http\Requests\Operations\Projects\StoreProjectRequest;
use App\Http\Requests\Operations\Projects\UpdateProjectRequest;
use App\Models\Branch;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\ExpenseLine;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\Site;
use App\Models\TenantCurrency;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\EquipmentScopeSummary;
use App\Services\ProjectBoqSummary;
use App\Services\ProjectPerformanceSummary;
use App\Services\TenantContext;
use App\Support\Operations\PresentsLinkedDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController
{
    use PresentsLinkedDocuments;

    public function index(): Response
    {
        Gate::authorize('viewAny', Project::class);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $tenant = resolve(TenantContext::class)->current();
        $canFilterBranches = $tenant->is_multibranch && $user->can('branches.view-all');
        $projects = Project::query()
            ->with(['branch.country', 'customer', 'contract', 'manager'])
            ->withCount(['sites', 'activities'])
            ->visibleTo($user)
            ->orderBy('name')
            ->get();
        $canViewProjectCosts = $user->can('daily-site-reports.view-costs')
            || $user->can('expenses.view-costs')
            || $user->can('estimates.view-costs')
            || $user->can('finance.reports.view')
            || $user->can('projects.update')
            || $user->can('projects.view-all')
            || $user->can('project-activities.manage');
        $reportCosts = collect();
        $expenseCosts = collect();

        if ($canViewProjectCosts && $projects->isNotEmpty()) {
            $projectIds = $projects->modelKeys();
            $reportCosts = DailySiteReport::query()
                ->whereIn('project_id', $projectIds)
                ->where('status', DailySiteReport::STATUS_APPROVED)
                ->selectRaw('project_id, SUM(input_cost) as total')
                ->groupBy('project_id')
                ->pluck('total', 'project_id');
            $expenseCosts = ExpenseLine::query()
                ->whereIn('project_id', $projectIds)
                ->whereHas('expense', fn (Builder $query): Builder => $query->where('status', 'approved'))
                ->selectRaw('project_id, SUM(base_currency_amount) as total')
                ->groupBy('project_id')
                ->pluck('total', 'project_id');
        }

        return Inertia::render('operations/projects/index', [
            'projects' => $projects->map(function (Project $project) use ($canViewProjectCosts, $reportCosts, $expenseCosts): array {
                $row = $this->projectRow($project);
                $recordedCost = (float) ($reportCosts->get($project->id) ?? 0)
                    + (float) ($expenseCosts->get($project->id) ?? 0);

                return [
                    ...$row,
                    'recorded_cost_amount' => $canViewProjectCosts ? number_format($recordedCost, 4, '.', '') : null,
                    'recorded_cost_currency_code' => $canViewProjectCosts ? $project->branch->default_currency_code : null,
                ];
            }),
            'branchFilter' => [
                'visible' => $canFilterBranches,
                'branches' => $canFilterBranches
                    ? Branch::query()->where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get(['id', 'name'])->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name])->values()->all()
                    : [],
            ],
            ...$this->formOptions($user),
        ]);
    }

    public function show(Project $project, EquipmentScopeSummary $equipmentSummary, ProjectPerformanceSummary $performanceSummary, ProjectBoqSummary $boqSummary): Response
    {
        Gate::authorize('view', $project);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $project->load(['branch.country', 'customer', 'contract', 'manager', 'users', 'sites.manager', 'activities.site']);
        $canViewFleet = $user->can('equipment.view');
        $canViewEstimates = $user->can('estimates.view');
        $canViewEstimateCosts = $user->can('estimates.view-costs');

        return Inertia::render('operations/projects/show', [
            'project' => $this->projectRow($project),
            'sites' => $project->sites
                ->sortBy('name')
                ->values()
                ->map(fn (Site $site): array => [
                    'id' => $site->id,
                    'project_id' => $site->project_id,
                    'reference' => $site->reference,
                    'name' => $site->name,
                    'location_name' => $site->location_name,
                    'manager_id' => $site->manager_id,
                    'manager_name' => $site->manager?->name,
                    'reporting_deadline' => $site->reporting_deadline,
                    'status' => $site->status,
                    'can_update' => Gate::forUser($user)->allows('update', $site),
                    'can_archive' => Gate::forUser($user)->allows('delete', $site),
                ]),
            'activities' => $project->activities
                ->sortBy('sort_order')
                ->values()
                ->map(fn (ProjectActivity $activity): array => $this->activityRow($activity, $this->canViewRates($user))),
            'estimates' => $canViewEstimates ? ProjectEstimate::query()
                ->with('approver')
                ->withCount('lines')
                ->where('project_id', $project->id)
                ->orderByDesc('version_number')
                ->get()
                ->map(fn (ProjectEstimate $estimate): array => [
                    'id' => $estimate->id,
                    'version_number' => $estimate->version_number,
                    'title' => $estimate->title,
                    'status' => $estimate->status->value,
                    'status_label' => $estimate->status->label(),
                    'is_baseline' => $estimate->is_baseline,
                    'currency_code' => $estimate->currency_code,
                    'lines_count' => $estimate->lines_count,
                    'approved_by' => $estimate->approver?->name,
                    'approved_at' => $estimate->approved_at?->toDateTimeString(),
                ]) : [],
            'boq' => fn (): ?array => $canViewEstimates ? $boqSummary->forProject($project, $performanceSummary) : null,
            'activeTab' => request()->query('tab', 'sites'),
            'performance' => $canViewEstimates ? $performanceSummary->forProject($project, $canViewEstimateCosts) : null,
            'assignedUsers' => $project->users
                ->map(fn (User $assignedUser): array => [
                    'id' => $assignedUser->id,
                    'name' => $assignedUser->name,
                    'email' => $assignedUser->email,
                    'role' => $assignedUser->pivot->getAttribute('role'),
                    'can_manage' => (bool) $assignedUser->pivot->getAttribute('can_manage'),
                ]),
            'documents' => $this->linkedDocumentsFor($project, $user),
            'dsrSummary' => $this->dailySiteReportSummary($project, $this->canViewRates($user)),
            'fleet' => $canViewFleet ? $equipmentSummary->forProject($project, $user) : null,
            'canViewFleet' => $canViewFleet,
            'canUpdateProject' => Gate::forUser($user)->allows('update', $project),
            'canCreateSite' => Gate::forUser($user)->allows('create', [Site::class, $project]),
            'canUploadDocuments' => Gate::forUser($user)->allows('create', Document::class),
            'canViewRates' => $this->canViewRates($user),
            'canViewEstimates' => $canViewEstimates,
            'canCreateEstimate' => Gate::forUser($user)->allows('create', [ProjectEstimate::class, $project]),
            ...$this->formOptions($user),
            ...$this->documentFormOptions($user),
        ]);
    }

    public function store(StoreProjectRequest $request, SaveProject $action): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id: string, customer_id?: string|null, contract_id?: string|null, reference: string, name: string, project_type?: string|null, location?: string|null, description?: string|null, manager_id?: string|null, base_currency_code: string, budget_amount?: string|null, starts_on?: string|null, ends_on?: string|null, reporting_deadline?: string|null, status: string} $data */
        $data = $request->validated();
        $project = $action->handle($data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project saved.']);

        return to_route('projects.show', $project);
    }

    public function update(UpdateProjectRequest $request, Project $project, SaveProject $action): RedirectResponse
    {
        Gate::authorize('update', $project);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id: string, customer_id?: string|null, contract_id?: string|null, reference: string, name: string, project_type?: string|null, location?: string|null, description?: string|null, manager_id?: string|null, base_currency_code: string, budget_amount?: string|null, starts_on?: string|null, ends_on?: string|null, reporting_deadline?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $project);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project updated.']);

        return to_route('projects.index');
    }

    public function destroy(Project $project, AuditLogger $auditLogger, EndProjectDeployments $endDeployments): RedirectResponse
    {
        Gate::authorize('delete', $project);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        DB::transaction(function () use ($project, $auditLogger, $endDeployments, $actor): void {
            $project = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $oldStatus = $project->status;
            $newStatus = $oldStatus === 'archived' ? 'active' : 'archived';

            $project->update(['status' => $newStatus]);
            if ($newStatus === 'archived') {
                $endDeployments->handle($project, $actor);
            }

            $auditLogger->record(
                event: 'operations.project.status_changed',
                subject: $project,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $newStatus],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project archive status changed.']);

        return to_route('projects.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $branchIds = $branchContext->accessibleBranchIds($user);
        $defaultBranch = $branchContext->current($user) ?? $branchContext->operationalDefault($user);

        return [
            'defaultBranchId' => $defaultBranch?->id,
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('id', $branchIds)
                ->orderBy('name')
                ->with('country')
                ->get(['id', 'name', 'country_code'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name, 'country_code' => $branch->country_code, 'country_name' => $branch->country->name]),
            'customers' => Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->visibleTo($user)
                ->orderBy('name')
                ->get(['id', 'name', 'branch_id'])
                ->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->name, 'branch_id' => $customer->branch_id]),
            'contracts' => Contract::query()
                ->where('tenant_id', $tenantId)
                ->visibleTo($user)
                ->orderBy('reference')
                ->get(['id', 'reference', 'title', 'branch_id', 'customer_id'])
                ->map(fn (Contract $contract): array => ['id' => $contract->id, 'name' => sprintf('%s - %s', $contract->reference, $contract->title), 'branch_id' => $contract->branch_id, 'customer_id' => $contract->customer_id]),
            'users' => User::query()
                ->with('staff')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereHas('branches', fn (Builder $query) => $query->whereIn('branches.id', $branchIds))
                ->orderBy('name')
                ->get(['id', 'staff_id', 'name', 'email'])
                ->map(fn (User $optionUser): array => [
                    'id' => $optionUser->id,
                    'name' => sprintf('%s (%s)', $optionUser->name, $optionUser->email),
                    'email' => $optionUser->email,
                    'branch_ids' => $optionUser->branches()->pluck('branches.id')->values()->all(),
                    'can_view_all_branches' => $optionUser->can('branches.view-all'),
                ]),
            'activityUnits' => UnitOfMeasure::query()
                ->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['code', 'name', 'symbol'])
                ->map(fn (UnitOfMeasure $unit): array => [
                    'id' => $unit->symbol ?: $unit->code,
                    'name' => sprintf('%s (%s)', $unit->name, $unit->symbol ?: $unit->code),
                ]),
            'currencies' => TenantCurrency::query()
                ->with('currency')
                ->where('tenant_id', $tenantId)
                ->where('is_enabled', true)
                ->orderBy('currency_code')
                ->get()
                ->map(fn (TenantCurrency $currency): array => ['id' => $currency->currency_code, 'name' => sprintf('%s - %s', $currency->currency_code, $currency->currency->name)]),
            'projectTypes' => collect(ProjectType::cases())->map(fn (ProjectType $type): array => ['id' => $type->value, 'name' => $type->label()])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectRow(Project $project): array
    {
        return [
            'id' => $project->id,
            'branch_id' => $project->branch_id,
            'customer_id' => $project->customer_id,
            'contract_id' => $project->contract_id,
            'reference' => $project->reference,
            'name' => $project->name,
            'project_type' => $project->project_type?->value,
            'project_type_label' => $project->project_type?->label(),
            'location' => $project->location,
            'description' => $project->description,
            'manager_id' => $project->manager_id,
            'manager_name' => $project->manager?->name,
            'branch_name' => $project->branch->name,
            'branch_country_name' => $project->branch->country->name,
            'customer_name' => $project->customer?->name,
            'contract_reference' => $project->contract?->reference,
            'base_currency_code' => $project->base_currency_code,
            'budget_amount' => $project->budget_amount,
            'starts_on' => $project->starts_on?->toDateString(),
            'ends_on' => $project->ends_on?->toDateString(),
            'reporting_deadline' => $project->reporting_deadline,
            'status' => $project->status,
            'sites_count' => $project->sites_count ?? $project->sites()->count(),
            'activities_count' => $project->activities_count ?? $project->activities()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityRow(ProjectActivity $activity, bool $canViewRates): array
    {
        return [
            'id' => $activity->id,
            'project_id' => $activity->project_id,
            'site_id' => $activity->site_id,
            'managed_by_estimate' => $activity->estimate_line_id !== null,
            'site_name' => $activity->site?->name,
            'code' => $activity->code,
            'boq_item_number' => $activity->boq_item_number,
            'name' => $activity->name,
            'unit' => $activity->unit,
            'planned_quantity' => $activity->planned_quantity,
            'approved_quantity' => $activity->approved_quantity,
            'rate_amount' => $canViewRates ? $activity->rate_amount : null,
            'currency_code' => $activity->currency_code,
            'status' => $activity->status,
            'sort_order' => $activity->sort_order,
        ];
    }

    private function canViewRates(User $user): bool
    {
        if ($user->can('project-activities.manage')) {
            return true;
        }

        if ($user->can('daily-site-reports.view-costs')) {
            return true;
        }

        if ($user->can('projects.update')) {
            return true;
        }

        if ($user->can('projects.view-all')) {
            return true;
        }

        return $user->can('finance.reports.view');
    }

    /**
     * @return array<string, mixed>
     */
    private function dailySiteReportSummary(Project $project, bool $canViewCosts): array
    {
        $reports = DailySiteReport::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->get();

        return [
            'draft' => $reports->where('status', DailySiteReport::STATUS_DRAFT)->count(),
            'pending' => $reports->whereIn('status', [DailySiteReport::STATUS_SUBMITTED, DailySiteReport::STATUS_REVIEWED])->count(),
            'returned' => $reports->where('status', DailySiteReport::STATUS_RETURNED)->count(),
            'missing' => $reports->where('status', DailySiteReport::STATUS_MISSING)->count(),
            'approved' => $reports->where('status', DailySiteReport::STATUS_APPROVED)->count(),
            'output_value' => $canViewCosts ? $reports->sum(fn (DailySiteReport $report): float => (float) $report->output_value) : null,
            'input_cost' => $canViewCosts ? $reports->sum(fn (DailySiteReport $report): float => (float) $report->input_cost) : null,
            'profit_loss' => $canViewCosts ? $reports->sum(fn (DailySiteReport $report): float => (float) $report->profit_loss) : null,
        ];
    }
}
