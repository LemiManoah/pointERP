<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Projects\SaveProject;
use App\Http\Requests\Operations\Projects\StoreProjectRequest;
use App\Http\Requests\Operations\Projects\UpdateProjectRequest;
use App\Models\Branch;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\EquipmentScopeSummary;
use App\Services\TenantContext;
use App\Support\Operations\PresentsLinkedDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
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

        return Inertia::render('operations/projects/index', [
            'projects' => Project::query()
                ->with(['branch', 'customer', 'contract', 'manager'])
                ->withCount(['sites', 'activities'])
                ->visibleTo($user)
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project): array => $this->projectRow($project)),
            ...$this->formOptions($user),
        ]);
    }

    public function show(Project $project, EquipmentScopeSummary $equipmentSummary): Response
    {
        Gate::authorize('view', $project);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $project->load(['branch', 'customer', 'contract', 'manager', 'users', 'sites.manager', 'activities.site']);
        $canViewFleet = $user->can('equipment.view');

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
                ]),
            'activities' => $project->activities
                ->sortBy('sort_order')
                ->values()
                ->map(fn (ProjectActivity $activity): array => $this->activityRow($activity, $this->canViewRates($user))),
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
            'canUploadDocuments' => Gate::forUser($user)->allows('create', Document::class),
            'canViewRates' => $this->canViewRates($user),
            ...$this->formOptions($user),
            ...$this->documentFormOptions($user),
        ]);
    }

    public function store(StoreProjectRequest $request, SaveProject $action): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id: string, customer_id?: string|null, contract_id?: string|null, reference: string, name: string, description?: string|null, manager_id?: string|null, base_currency_code: string, budget_amount?: string|null, starts_on?: string|null, ends_on?: string|null, reporting_deadline?: string|null, status: string} $data */
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

        /** @var array{branch_id: string, customer_id?: string|null, contract_id?: string|null, reference: string, name: string, description?: string|null, manager_id?: string|null, base_currency_code: string, budget_amount?: string|null, starts_on?: string|null, ends_on?: string|null, reporting_deadline?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $project);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project updated.']);

        return to_route('projects.show', $project);
    }

    public function destroy(Project $project, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $oldStatus = $project->status;
        $newStatus = $oldStatus === 'archived' ? 'active' : 'archived';

        $project->update(['status' => $newStatus]);
        $auditLogger->record(
            event: 'operations.project.status_changed',
            subject: $project,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project archive status changed.']);

        return to_route('projects.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user);

        return [
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('id', $branchIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name]),
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
                ->map(fn (User $optionUser): array => ['id' => $optionUser->id, 'name' => sprintf('%s (%s)', $optionUser->name, $optionUser->email), 'email' => $optionUser->email]),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Currency $currency): array => ['id' => $currency->code, 'name' => sprintf('%s - %s', $currency->code, $currency->name)]),
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
            'description' => $project->description,
            'manager_id' => $project->manager_id,
            'manager_name' => $project->manager?->name,
            'branch_name' => $project->branch->name,
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
