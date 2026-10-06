<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoqProgressEntry;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateLine;
use App\Models\WorkItemTemplate;
use Illuminate\Support\Facades\Gate;

final class ProjectBoqSummary
{
    /** @return array<string, mixed> */
    public function forProject(Project $project, ProjectPerformanceSummary $performance, ?ProjectEstimate $revision = null): array
    {
        Gate::authorize('view', $project);
        Gate::authorize('viewAny', ProjectEstimate::class);
        $baseline = $revision ?? ProjectEstimate::query()->with('lines.unit')->where('project_id', $project->id)->where('is_baseline', true)->first();
        $baseline?->loadMissing('lines.unit');
        $historical = $baseline !== null && ! $baseline->is_baseline;
        $canViewCosts = $baseline && Gate::allows('viewCosts', $baseline);
        $activities = ProjectActivity::query()->where('project_id', $project->id)->whereNotNull('boq_item_id')
            ->orderBy('sort_order')->get();
        $reports = DailySiteReport::query()->where('project_id', $project->id)->latest('report_date')->get();
        $reportIds = $reports->filter(fn (DailySiteReport $report): bool => Gate::allows('view', $report))->pluck('id');
        $entries = BoqProgressEntry::query()->where('project_id', $project->id)
            ->leftJoin('daily_site_report_work_lines as lines', 'lines.id', '=', 'boq_progress_entries.daily_site_report_work_line_id')
            ->latest('measurement_date')->select('boq_progress_entries.*', 'lines.daily_site_report_id')->get();

        return [
            'project' => $project->only(['id', 'name', 'reference']),
            'performance' => $performance->forProject($project, $canViewCosts, $revision),
            'historical' => $historical,
            'archivedItems' => ProjectEstimateLine::query()->with('estimate')
                ->whereHas('estimate', fn ($query) => $query->where('project_id', $project->id)->whereNotNull('approved_at'))
                ->whereNotNull('boq_item_id')
                ->whereNotIn('boq_item_id', ProjectEstimateLine::query()->select('boq_item_id')->whereNotNull('boq_item_id')
                    ->whereHas('estimate', fn ($query) => $query->where('project_id', $project->id)->where('is_baseline', true)))
                ->get()->sortByDesc(fn (ProjectEstimateLine $line): int => $line->estimate->version_number)
                ->unique('boq_item_id')->map(fn (ProjectEstimateLine $line): array => [
                    'id' => $line->boq_item_id, 'name' => $line->name, 'reference' => $line->boq_reference,
                    'revision' => $line->estimate->version_number,
                ])->values()->all(),
            'items' => $baseline?->lines->map(fn (ProjectEstimateLine $line): array => [
                'id' => $line->boq_item_id, 'name' => $line->name, 'description' => $line->description,
                'site_id' => $line->site_id, 'item_type' => $line->item_type->value,
                'unit' => $line->unit->symbol ?? $line->unit->code,
                'unit_of_measure_id' => $line->unit_of_measure_id,
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
            'activityTemplates' => Gate::allows('viewAny', WorkItemTemplate::class)
                ? WorkItemTemplate::query()->with('unit')->where('is_active', true)->orderBy('name')->get()
                    ->map(fn (WorkItemTemplate $template): array => [
                        'id' => $template->id, 'name' => $template->name,
                        'category' => $template->category, 'code' => $template->code,
                        'unit_of_measure_id' => $template->unit_of_measure_id,
                        'unit' => $template->unit->symbol ?? $template->unit->code,
                    ])->all()
                : [],
            'can' => [
                'createActivity' => ! $historical && Gate::allows('create', [ProjectActivity::class, $project]),
                'createEstimate' => Gate::allows('create', [ProjectEstimate::class, $project]),
                'viewCosts' => $canViewCosts,
                'viewActivityLibrary' => Gate::allows('viewAny', WorkItemTemplate::class),
            ],
        ];
    }
}
