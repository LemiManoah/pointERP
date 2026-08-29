<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Estimates\SaveProjectEstimate;
use App\Enums\EstimateResourceType;
use App\Http\Requests\Operations\Estimates\StoreProjectEstimateRequest;
use App\Models\EstimateResourceLine;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use App\Models\Site;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** @phpstan-import-type ProjectEstimatePayload from StoreProjectEstimateRequest */
final class ProjectEstimateController
{
    public function create(Project $project): Response
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);

        $baseline = ProjectEstimate::query()
            ->with(['lines.resources'])
            ->where('project_id', $project->id)
            ->where('is_baseline', true)
            ->first();

        return $this->editor($project, null, $baseline);
    }

    public function store(StoreProjectEstimateRequest $request, Project $project, SaveProjectEstimate $action): RedirectResponse
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var ProjectEstimatePayload $data */
        $data = $request->validated();
        $estimate = $action->handle($project, $data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estimate draft saved.']);

        return to_route('project-estimates.show', $estimate);
    }

    public function show(ProjectEstimate $projectEstimate): Response
    {
        Gate::authorize('view', $projectEstimate);

        return $this->editor($projectEstimate->project, $projectEstimate);
    }

    public function update(StoreProjectEstimateRequest $request, ProjectEstimate $projectEstimate, SaveProjectEstimate $action): RedirectResponse
    {
        Gate::authorize('update', $projectEstimate);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var ProjectEstimatePayload $data */
        $data = $request->validated();
        $action->handle($projectEstimate->project, $data, $actor, $projectEstimate);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estimate draft updated.']);

        return to_route('project-estimates.show', $projectEstimate);
    }

    public function destroy(ProjectEstimate $projectEstimate): RedirectResponse
    {
        Gate::authorize('delete', $projectEstimate);
        $project = $projectEstimate->project;
        $projectEstimate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft estimate deleted.']);

        return to_route('projects.show', $project);
    }

    private function editor(Project $project, ?ProjectEstimate $estimate, ?ProjectEstimate $source = null): Response
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        $tenantId = resolve(TenantContext::class)->id();
        $record = $estimate ?? $source;
        $record?->loadMissing(['lines.resources', 'lines.unit', 'approver']);
        $canViewCosts = $estimate instanceof ProjectEstimate
            ? Gate::forUser($user)->allows('viewCosts', $estimate)
            : true;

        return Inertia::render('operations/projects/estimates/editor', [
            'project' => ['id' => $project->id, 'reference' => $project->reference, 'name' => $project->name, 'base_currency_code' => $project->base_currency_code],
            'estimate' => $estimate instanceof ProjectEstimate ? $this->estimateData($estimate, true, $canViewCosts) : null,
            'source' => ! $estimate instanceof ProjectEstimate && $source instanceof ProjectEstimate ? $this->estimateData($source, false, true) : null,
            'sites' => Site::query()->where('project_id', $project->id)->whereIn('status', ['planned', 'active', 'suspended'])->orderBy('name')->get(['id', 'name'])->map(fn (Site $site): array => ['value' => $site->id, 'label' => $site->name]),
            'units' => UnitOfMeasure::query()->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->where('is_active', true)->orderBy('name')->get(['id', 'name', 'symbol'])->map(fn (UnitOfMeasure $unit): array => ['value' => $unit->id, 'label' => sprintf('%s%s', $unit->name, $unit->symbol ? ' ('.$unit->symbol.')' : '')]),
            'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'stock_unit_id', 'default_unit_cost'])->map(fn (InventoryItem $item): array => ['value' => $item->id, 'label' => sprintf('%s - %s', $item->code, $item->name), 'unit_id' => $item->stock_unit_id, 'unit_cost' => $item->default_unit_cost]),
            'resourceTypes' => collect(EstimateResourceType::cases())->map(fn (EstimateResourceType $type): array => ['value' => $type->value, 'label' => $type->label()]),
            'can' => [
                'update' => $estimate instanceof ProjectEstimate && Gate::forUser($user)->allows('update', $estimate),
                'approve' => $estimate instanceof ProjectEstimate && Gate::forUser($user)->allows('approve', $estimate),
                'viewCosts' => $canViewCosts,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function estimateData(ProjectEstimate $estimate, bool $includeStatus, bool $canViewCosts): array
    {
        return [
            'id' => $estimate->id,
            'version_number' => $estimate->version_number,
            'title' => $estimate->title,
            'currency_code' => $estimate->currency_code,
            'notes' => $estimate->notes,
            'status' => $includeStatus ? $estimate->status->value : null,
            'status_label' => $includeStatus ? $estimate->status->label() : null,
            'is_baseline' => $includeStatus && $estimate->is_baseline,
            'approved_by' => $estimate->approver?->name,
            'approved_at' => $estimate->approved_at?->toDateTimeString(),
            'lines' => $estimate->lines->map(fn (ProjectEstimateLine $line): array => [
                'work_item_key' => $line->work_item_key,
                'site_id' => $line->site_id,
                'unit_of_measure_id' => $line->unit_of_measure_id,
                'boq_reference' => $line->boq_reference,
                'code' => $line->code,
                'name' => $line->name,
                'planned_quantity' => $line->planned_quantity,
                'selling_rate' => $canViewCosts ? $line->selling_rate : null,
                'estimated_unit_cost' => $canViewCosts ? $line->estimated_unit_cost : null,
                'notes' => $line->notes,
                'resources' => $line->resources->map(fn (EstimateResourceLine $resource): array => [
                    'resource_type' => $resource->resource_type->value,
                    'inventory_item_id' => $resource->inventory_item_id,
                    'unit_of_measure_id' => $resource->unit_of_measure_id,
                    'name' => $resource->name,
                    'quantity_per_work_unit' => $resource->quantity_per_work_unit,
                    'estimated_unit_cost' => $canViewCosts ? $resource->estimated_unit_cost : null,
                    'notes' => $resource->notes,
                ])->all(),
            ])->all(),
        ];
    }
}
