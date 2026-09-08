<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Estimates\DeleteWorkItemTemplate;
use App\Actions\Operations\Estimates\SaveWorkItemTemplate;
use App\Enums\EstimateResourceType;
use App\Http\Requests\Operations\Estimates\StoreWorkItemTemplateRequest;
use App\Models\InventoryItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkItemResourceTemplate;
use App\Models\WorkItemTemplate;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** @phpstan-import-type WorkItemTemplatePayload from StoreWorkItemTemplateRequest */
final class WorkItemTemplateController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', WorkItemTemplate::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $tenantId = resolve(TenantContext::class)->id();

        $category = $request->query('category');
        $search = $request->query('search');

        $query = WorkItemTemplate::query()
            ->with(['unit', 'resources.unit', 'resources.inventoryItem'])
            ->when(is_string($category) && $category !== '', fn (Builder $q) => $q->where('category', $category))
            ->when(is_string($search) && $search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->orderBy('category')
            ->orderBy('name');

        $templates = $query->paginate(20)->withQueryString();

        $categories = WorkItemTemplate::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        $units = UnitOfMeasure::query()
            ->where(fn (Builder $q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'symbol'])
            ->map(fn (UnitOfMeasure $unit): array => [
                'value' => $unit->id,
                'label' => sprintf('%s%s', $unit->name, $unit->symbol ? ' ('.$unit->symbol.')' : ''),
            ]);

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'stock_unit_id', 'default_unit_cost'])
            ->map(fn (InventoryItem $item): array => [
                'value' => $item->id,
                'label' => sprintf('%s - %s', $item->code, $item->name),
                'unit_id' => $item->stock_unit_id,
                'unit_cost' => $item->default_unit_cost,
            ]);

        $resourceTypes = collect(EstimateResourceType::cases())->map(fn (EstimateResourceType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ]);

        return Inertia::render('operations/work-item-templates/index', [
            'templates' => $templates->through(fn (WorkItemTemplate $template): array => [
                'id' => $template->id,
                'code' => $template->code,
                'category' => $template->category,
                'name' => $template->name,
                'unit_of_measure_id' => $template->unit_of_measure_id,
                'unit_name' => $template->unit->name,
                'unit_symbol' => $template->unit->symbol,
                'default_selling_rate' => $template->default_selling_rate,
                'default_unit_cost' => $template->default_unit_cost,
                'specifications' => $template->specifications,
                'is_active' => $template->is_active,
                'resources' => $template->resources->map(fn (WorkItemResourceTemplate $res): array => [
                    'id' => $res->id,
                    'resource_type' => $res->resource_type->value,
                    'resource_type_label' => $res->resource_type->label(),
                    'inventory_item_id' => $res->inventory_item_id,
                    'unit_of_measure_id' => $res->unit_of_measure_id,
                    'name' => $res->name,
                    'quantity_per_work_unit' => $res->quantity_per_work_unit,
                    'unit_cost' => $res->effectiveUnitCost(),
                    'notes' => $res->notes,
                    'unit_symbol' => $res->unit?->symbol,
                ])->all(),
            ]),
            'categories' => $categories,
            'units' => $units,
            'items' => $items,
            'resourceTypes' => $resourceTypes,
            'currencyCode' => resolve(TenantContext::class)->current()->default_currency_code,
            'filters' => [
                'category' => is_string($category) ? $category : '',
                'search' => is_string($search) ? $search : '',
            ],
            'can' => [
                'create' => Gate::forUser($user)->allows('create', WorkItemTemplate::class),
                'manage' => $user->can('work-item-templates.manage'),
            ],
        ]);
    }

    public function store(StoreWorkItemTemplateRequest $request, SaveWorkItemTemplate $action): RedirectResponse
    {
        Gate::authorize('create', WorkItemTemplate::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $tenant = resolve(TenantContext::class)->current();

        /** @var WorkItemTemplatePayload $data */
        $data = $request->validated();
        $action->handle($tenant, $data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Work activity template created.']);

        return to_route('work-item-templates.index');
    }

    public function update(StoreWorkItemTemplateRequest $request, WorkItemTemplate $workItemTemplate, SaveWorkItemTemplate $action): RedirectResponse
    {
        Gate::authorize('update', $workItemTemplate);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $tenant = resolve(TenantContext::class)->current();

        /** @var WorkItemTemplatePayload $data */
        $data = $request->validated();
        $action->handle($tenant, $data, $actor, $workItemTemplate);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Work activity template updated.']);

        return to_route('work-item-templates.index');
    }

    public function destroy(WorkItemTemplate $workItemTemplate, DeleteWorkItemTemplate $action): RedirectResponse
    {
        Gate::authorize('delete', $workItemTemplate);

        $actor = request()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($workItemTemplate, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Work activity template deleted.']);

        return to_route('work-item-templates.index');
    }
}
