<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\SaveDailySiteReport;
use App\Http\Requests\Operations\DailySiteReports\StoreDailySiteReportRequest;
use App\Http\Requests\Operations\DailySiteReports\UpdateDailySiteReportRequest;
use App\Models\Currency;
use App\Models\DailySiteReport;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DailySiteReportController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', DailySiteReport::class);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $reports = DailySiteReport::query()
            ->with(['project', 'site', 'submittedBy', 'approvedBy'])
            ->where('tenant_id', resolve(TenantContext::class)->id())
            ->latest('report_date')
            ->get()
            ->filter(fn (DailySiteReport $report): bool => Gate::forUser($user)->allows('view', $report))
            ->values();

        return Inertia::render('operations/daily-site-reports/index', [
            'reports' => $reports->map(fn (DailySiteReport $report): array => $this->reportRow($report)),
            ...$this->formOptions($user),
        ]);
    }

    public function show(DailySiteReport $dailySiteReport): Response
    {
        Gate::authorize('view', $dailySiteReport);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $dailySiteReport->load([
            'project',
            'site',
            'submittedBy',
            'approvedBy',
            'workLines',
            'labourLines',
            'equipmentLines',
            'materialLines',
            'costLines',
            'delayLines',
        ]);

        return Inertia::render('operations/daily-site-reports/show', [
            'report' => [
                ...$this->reportRow($dailySiteReport),
                'site_id' => $dailySiteReport->site_id,
                'weather' => $dailySiteReport->weather,
                'site_conditions' => $dailySiteReport->site_conditions,
                'work_summary' => $dailySiteReport->work_summary,
                'delay_summary' => $dailySiteReport->delay_summary,
                'visitor_summary' => $dailySiteReport->visitor_summary,
                'hse_notes' => $dailySiteReport->hse_notes,
                'environment_notes' => $dailySiteReport->environment_notes,
                'social_notes' => $dailySiteReport->social_notes,
                'completion_percent' => $dailySiteReport->completion_percent,
                'return_reason' => $dailySiteReport->return_reason,
                'work_lines' => $dailySiteReport->workLines->values(),
                'labour_lines' => $dailySiteReport->labourLines->values(),
                'equipment_lines' => $dailySiteReport->equipmentLines->values(),
                'material_lines' => $dailySiteReport->materialLines->values(),
                'cost_lines' => $dailySiteReport->costLines->values(),
                'delay_lines' => $dailySiteReport->delayLines->values(),
            ],
            'can' => [
                'update' => Gate::forUser($user)->allows('update', $dailySiteReport),
                'submit' => Gate::forUser($user)->allows('submit', $dailySiteReport),
                'approve' => Gate::forUser($user)->allows('approve', $dailySiteReport),
                'return' => Gate::forUser($user)->allows('return', $dailySiteReport),
            ],
            ...$this->formOptions($user),
        ]);
    }

    public function store(StoreDailySiteReportRequest $request, SaveDailySiteReport $action): RedirectResponse
    {
        $site = Site::query()->findOrFail($request->validated('site_id'));
        Gate::authorize('create', [DailySiteReport::class, $site]);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $existingReport = DailySiteReport::query()
            ->where('site_id', $site->id)
            ->whereDate('report_date', (string) $data['report_date'])
            ->first();

        if ($existingReport instanceof DailySiteReport) {
            return to_route('daily-site-reports.show', $existingReport);
        }

        $report = $action->handle($data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily site report saved.']);

        return to_route('daily-site-reports.show', $report);
    }

    public function update(UpdateDailySiteReportRequest $request, DailySiteReport $dailySiteReport, SaveDailySiteReport $action): RedirectResponse
    {
        Gate::authorize('update', $dailySiteReport);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $data['site_id'] = $dailySiteReport->site_id;
        $action->handle($data, $actor, $dailySiteReport);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily site report updated.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }

    public function destroy(DailySiteReport $dailySiteReport): RedirectResponse
    {
        Gate::authorize('update', $dailySiteReport);

        $dailySiteReport->update(['status' => DailySiteReport::STATUS_ARCHIVED]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily site report archived.']);

        return to_route('daily-site-reports.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user);

        $sites = Site::query()
            ->with('project')
            ->where('tenant_id', $tenantId)
            ->whereIn('branch_id', $branchIds)
            ->orderBy('name')
            ->get()
            ->filter(fn (Site $site): bool => Gate::forUser($user)->allows('view', $site))
            ->values();

        return [
            'sites' => $sites->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => sprintf('%s (%s)', $site->name, $site->project->reference),
                'project_id' => $site->project_id,
            ]),
            'activities' => ProjectActivity::query()
                ->with('project')
                ->where('tenant_id', $tenantId)
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->get()
                ->filter(fn (ProjectActivity $activity): bool => Gate::forUser($user)->allows('view', $activity))
                ->values()
                ->map(fn (ProjectActivity $activity): array => [
                    'id' => $activity->id,
                    'project_id' => $activity->project_id,
                    'site_id' => $activity->site_id,
                    'label' => mb_trim(($activity->boq_item_number ? $activity->boq_item_number.' - ' : '').$activity->name),
                    'boq_item_number' => $activity->boq_item_number,
                    'unit' => $activity->unit,
                    'rate_amount' => $this->canViewRates($user) ? $activity->rate_amount : null,
                    'currency_code' => $activity->currency_code,
                ]),
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
    private function reportRow(DailySiteReport $report): array
    {
        return [
            'id' => $report->id,
            'reference' => $report->reference,
            'project_name' => $report->project->name,
            'site_name' => $report->site->name,
            'report_date' => $report->report_date?->toDateString(),
            'status' => $report->status,
            'output_value' => $report->output_value,
            'input_cost' => $report->input_cost,
            'profit_loss' => $report->profit_loss,
            'submitted_by' => $report->submittedBy?->name,
            'approved_by' => $report->approvedBy?->name,
        ];
    }

    private function canViewRates(User $user): bool
    {
        if ($user->can('project-activities.manage')) {
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
}
