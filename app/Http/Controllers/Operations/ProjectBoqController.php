<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\ProjectActivities\SaveProjectActivity;
use App\Http\Requests\Operations\ProjectActivities\StoreProjectActivityRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use App\Models\User;
use App\Services\ProjectBoqSummary;
use App\Services\ProjectPerformanceSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** @phpstan-import-type ProjectActivityPayload from SaveProjectActivity */
final class ProjectBoqController
{
    public function show(Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);
        Gate::authorize('viewAny', ProjectEstimate::class);

        return to_route('projects.show', ['project' => $project, 'tab' => 'boq']);
    }

    public function item(Project $project, string $item, ProjectBoqSummary $summary, ProjectPerformanceSummary $performance): Response
    {
        Gate::authorize('view', $project);
        Gate::authorize('viewAny', ProjectEstimate::class);
        abort_unless(ProjectEstimateLine::query()->where('boq_item_id', $item)
            ->whereHas('estimate', fn ($query) => $query->where('project_id', $project->id)->where('is_baseline', true))->exists(), 404);

        return Inertia::render('operations/projects/boq', [
            ...$summary->forProject($project, $performance),
            'itemId' => $item,
        ]);
    }

    public function storeActivity(StoreProjectActivityRequest $request, Project $project, SaveProjectActivity $action): RedirectResponse
    {
        Gate::authorize('view', $project);
        Gate::authorize('create', [ProjectActivity::class, $project]);
        /** @var ProjectActivityPayload $data */
        $data = $request->validated();
        abort_unless($data['project_id'] === $project->id && ! empty($data['boq_item_id']), 422);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($data, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Activity linked to the BoQ item.']);

        return to_route('projects.boq.item', ['project' => $project, 'item' => $data['boq_item_id']]);
    }
}
