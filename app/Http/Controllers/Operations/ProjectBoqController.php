<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\ProjectActivities\SaveProjectActivity;
use App\Http\Requests\Operations\ProjectActivities\StoreProjectActivityRequest;
use App\Models\BoqProgressEntry;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use App\Models\User;
use App\Services\ProjectPerformanceSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** @phpstan-import-type ProjectActivityPayload from SaveProjectActivity */
final class ProjectBoqController
{
    public function show(Project $project, ProjectPerformanceSummary $performance): Response
    {
        Gate::authorize('view', $project);
        Gate::authorize('viewAny', ProjectEstimate::class);
        $baseline = ProjectEstimate::query()->with('lines.unit')->where('project_id', $project->id)->where('is_baseline', true)->first();
        $canViewCosts = $baseline && Gate::allows('viewCosts', $baseline);
        $activities = ProjectActivity::query()->where('project_id', $project->id)->whereNotNull('boq_item_id')
            ->orderBy('sort_order')->get();
        $reports = DailySiteReport::query()->where('project_id', $project->id)->latest('report_date')->get();
        $reportIds = $reports->filter(fn (DailySiteReport $report): bool => Gate::allows('view', $report))->pluck('id');
        $entries = BoqProgressEntry::query()->where('project_id', $project->id)
            ->leftJoin('daily_site_report_work_lines as lines', 'lines.id', '=', 'boq_progress_entries.daily_site_report_work_line_id')
            ->latest('measurement_date')->select('boq_progress_entries.*', 'lines.daily_site_report_id')->get();

        return Inertia::render('operations/projects/boq', [
            'project' => $project->only(['id', 'name', 'reference']),
            'performance' => $performance->forProject($project, $canViewCosts),
            'items' => $baseline?->lines->map(fn (ProjectEstimateLine $line): array => [
                'id' => $line->boq_item_id, 'name' => $line->name, 'description' => $line->description,
                'site_id' => $line->site_id, 'item_type' => $line->item_type->value,
                'unit' => $line->unit->symbol ?? $line->unit->code,
                'source_document' => $line->source_document, 'source_sheet' => $line->source_sheet,
                'source_row' => $line->source_row,
            ])->values()->all() ?? [],
            'activities' => $activities->map(fn (ProjectActivity $activity): array => $activity->only([
                'id', 'boq_item_id', 'name', 'unit', 'progress_method', 'approved_quantity', 'status',
            ]))->all(),
            'measurements' => $entries->map(fn (BoqProgressEntry $entry): array => [
                'id' => $entry->id, 'boq_item_id' => $entry->boq_item_id,
                'activity_id' => $entry->project_activity_id, 'quantity' => $entry->quantity, 'unit' => $entry->unit,
                'date' => $entry->measurement_date->toDateString(),
                'description' => $entry->description, 'legacy' => str_starts_with($entry->source_key, 'legacy:'),
                'report_id' => $reportIds->contains($entry->getAttribute('daily_site_report_id')) ? $entry->getAttribute('daily_site_report_id') : null,
            ])->all(),
            'reports' => $reports->whereIn('id', $reportIds)->map(fn (DailySiteReport $report): array => [
                'id' => $report->id, 'reference' => $report->reference,
                'date' => $report->report_date->toDateString(), 'status' => $report->status,
            ])->values()->all(),
            'revisions' => ProjectEstimate::query()->where('project_id', $project->id)->orderByDesc('version_number')
                ->get(['id', 'title', 'version_number', 'status', 'is_baseline'])->toArray(),
            'sites' => $project->sites()->get(['id', 'name'])->toArray(),
            'can' => [
                'createActivity' => Gate::allows('create', [ProjectActivity::class, $project]),
                'createEstimate' => Gate::allows('create', [ProjectEstimate::class, $project]),
                'viewCosts' => $canViewCosts,
            ],
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

        return to_route('projects.boq.show', $project);
    }
}
