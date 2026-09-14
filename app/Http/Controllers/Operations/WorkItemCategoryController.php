<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Requests\Operations\Estimates\StoreWorkItemCategoryRequest;
use App\Models\User;
use App\Models\WorkItemCategory;
use App\Models\WorkItemTemplate;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class WorkItemCategoryController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', WorkItemTemplate::class);

        return Inertia::render('operations/work-item-categories/index', [
            'categories' => WorkItemCategory::query()->orderBy('name')->get()->map(fn (WorkItemCategory $category): array => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'is_active' => $category->is_active,
            ]),
        ]);
    }

    public function store(StoreWorkItemCategoryRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('create', WorkItemTemplate::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $data = $request->validated();
        $category = WorkItemCategory::query()->create([
            'tenant_id' => resolve(TenantContext::class)->id(),
            'name' => $data['name'],
            'code' => $this->code($data['name'], $data['code'] ?? null),
            'is_active' => $data['is_active'],
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $auditLogger->record('operations.work_item_category.created', $category, $actor, [], $category->toArray());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Work activity category created.']);

        return to_route('work-item-categories.index');
    }

    public function update(StoreWorkItemCategoryRequest $request, WorkItemCategory $workItemCategory, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('create', WorkItemTemplate::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $old = $workItemCategory->toArray();
        $data = $request->validated();
        $workItemCategory->update([
            'name' => $data['name'],
            'code' => $this->code($data['name'], $data['code'] ?? null, $workItemCategory),
            'is_active' => $data['is_active'],
            'updated_by' => $actor->id,
        ]);
        $auditLogger->record('operations.work_item_category.updated', $workItemCategory, $actor, $old, $workItemCategory->fresh()?->toArray() ?? []);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Work activity category updated.']);

        return to_route('work-item-categories.index');
    }

    public function destroy(WorkItemCategory $workItemCategory, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('create', WorkItemTemplate::class);
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $workItemCategory->update(['is_active' => ! $workItemCategory->is_active, 'updated_by' => $actor->id]);
        $auditLogger->record('operations.work_item_category.status_changed', $workItemCategory, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => $workItemCategory->is_active ? 'Category restored.' : 'Category deactivated.']);

        return to_route('work-item-categories.index');
    }

    private function code(string $name, ?string $requested, ?WorkItemCategory $ignore = null): string
    {
        $base = Str::upper(Str::slug($requested ?: $name, '_')) ?: 'CATEGORY';
        $code = Str::limit($base, 80, '');
        $suffix = 1;

        while (WorkItemCategory::query()->where('code', $code)->when($ignore instanceof WorkItemCategory, fn ($query) => $query->whereKeyNot($ignore->id))->exists()) {
            $code = Str::limit($base, 73, '').'_'.++$suffix;
        }

        return $code;
    }
}
