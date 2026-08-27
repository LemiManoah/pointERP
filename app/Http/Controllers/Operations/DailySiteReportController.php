<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\SaveDailySiteReport;
use App\Enums\InventoryMovementType;
use App\Http\Requests\Operations\DailySiteReports\StoreDailySiteReportRequest;
use App\Http\Requests\Operations\DailySiteReports\UpdateDailySiteReportRequest;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\DailySiteReportMaterialLine;
use App\Models\DailySiteReportReview;
use App\Models\DailySiteReportWorkLine;
use App\Models\Document;
use App\Models\DsrMaterialReconciliation;
use App\Models\Equipment;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventoryUnitConversion;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use App\Support\Operations\PresentsLinkedDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DailySiteReportController
{
    use PresentsLinkedDocuments;

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

        $canViewCosts = $this->canViewRates($user);
        $rows = $reports->map(fn (DailySiteReport $report): array => $this->reportRow($report, $canViewCosts));

        return Inertia::render('operations/daily-site-reports/index', [
            'reports' => $rows,
            'summary' => [
                'open' => $reports->whereIn('status', [DailySiteReport::STATUS_DRAFT])->count(),
                'pending' => $reports->whereIn('status', [DailySiteReport::STATUS_SUBMITTED, DailySiteReport::STATUS_REVIEWED])->count(),
                'returned' => $reports->where('status', DailySiteReport::STATUS_RETURNED)->count(),
                'missing' => $reports->where('status', DailySiteReport::STATUS_MISSING)->count(),
                'approved' => $reports->where('status', DailySiteReport::STATUS_APPROVED)->count(),
                'archived' => $reports->where('status', DailySiteReport::STATUS_ARCHIVED)->count(),
            ],
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
            'reviews.reviewer',
            'corrections.requester',
            'workLines',
            'labourLines',
            'equipmentLines',
            'materialLines',
            'materialLines.item.stockUnit',
            'materialLines.store',
            'materialLines.reconciliations.movement',
            'costLines',
            'delayLines',
        ]);
        $canViewCosts = $this->canViewRates($user);
        $canViewMaterialReconciliation = $user->can('inventory.dsr-reconciliation.view')
            && $dailySiteReport->isApproved();
        $linkedDocuments = $this->linkedDocumentsFor($dailySiteReport, $user);

        return Inertia::render('operations/daily-site-reports/show', [
            'report' => [
                ...$this->reportRow($dailySiteReport, $canViewCosts),
                'input_cost' => $canViewCosts ? $dailySiteReport->input_cost : null,
                'profit_loss' => $canViewCosts ? $dailySiteReport->profit_loss : null,
                'site_id' => $dailySiteReport->site_id,
                'project_id' => $dailySiteReport->project_id,
                'branch_id' => $dailySiteReport->site?->branch_id,
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
                'work_lines' => $this->workLineRows($dailySiteReport, $canViewCosts),
                'labour_lines' => $this->lineRows($dailySiteReport->labourLines->values()->all(), $canViewCosts),
                'equipment_lines' => $this->lineRows($dailySiteReport->equipmentLines->values()->all(), $canViewCosts),
                'material_lines' => $this->lineRows($dailySiteReport->materialLines->values()->all(), $canViewCosts),
                'cost_lines' => $canViewCosts ? $this->lineRows($dailySiteReport->costLines->values()->all(), true) : [],
                'delay_lines' => $dailySiteReport->delayLines->values(),
                'evidence_count' => count($linkedDocuments),
            ],
            'can' => [
                'update' => Gate::forUser($user)->allows('update', $dailySiteReport),
                'submit' => Gate::forUser($user)->allows('submit', $dailySiteReport),
                'approve' => Gate::forUser($user)->allows('approve', $dailySiteReport),
                'return' => Gate::forUser($user)->allows('return', $dailySiteReport),
                'correct' => Gate::forUser($user)->allows('correct', $dailySiteReport),
                'viewMaterialReconciliation' => $canViewMaterialReconciliation,
            ],
            'materialReconciliations' => $canViewMaterialReconciliation ? $dailySiteReport->materialLines->map(function (DailySiteReportMaterialLine $line) use ($dailySiteReport, $user): array {
                $reported = (float) ($line->stock_unit_quantity ?? $line->quantity ?? 0);
                $allocated = (float) $line->reconciliations->sum('allocated_quantity');
                $stockUnitLabel = $line->inventory_item_id === null
                    ? $line->unit
                    : ($line->item->stockUnit->symbol ?? $line->item->stockUnit->name);
                /** @var EloquentCollection<int, InventoryStockMovement> $candidates */
                $candidates = $line->inventory_item_id === null ? new EloquentCollection : InventoryStockMovement::query()
                    ->where('inventory_item_id', $line->inventory_item_id)->where('movement_type', InventoryMovementType::Issue->value)
                    ->where('project_id', $dailySiteReport->project_id)->where('site_id', $dailySiteReport->site_id)
                    ->whereBetween('posted_at', [$dailySiteReport->report_date->copy()->subDays(30)->startOfDay(), $dailySiteReport->report_date->copy()->addDays(7)->endOfDay()])
                    ->with(['store', 'postedBy'])->latest('posted_at')->limit(30)->get();

                return [
                    'id' => $line->id, 'material_name' => $line->material_name, 'inventory_item_id' => $line->inventory_item_id,
                    'inventory_store_id' => $line->inventory_store_id, 'status' => $line->inventory_reconciliation_status->value,
                    'reported_quantity' => $line->quantity, 'reported_unit' => $line->unit,
                    'stock_quantity' => $line->stock_unit_quantity ?? $line->quantity, 'stock_unit' => $stockUnitLabel,
                    'allocated_quantity' => (string) $allocated, 'outstanding_quantity' => (string) max(0, $reported - $allocated),
                    'external_reason' => $line->external_material_reason,
                    'allocations' => $line->reconciliations->map(fn (DsrMaterialReconciliation $allocation): array => ['id' => $allocation->id, 'type' => $allocation->type->value, 'quantity' => $allocation->allocated_quantity, 'reason' => $allocation->reason])->values()->all(),
                    'candidate_issues' => $candidates->map(function (InventoryStockMovement $movement): array {
                        $allocated = (float) DsrMaterialReconciliation::query()->where('inventory_stock_movement_id', $movement->id)->sum('allocated_quantity');

                        return ['id' => $movement->id, 'quantity' => (string) max(0, abs((float) $movement->quantity) - $allocated), 'store_name' => $movement->store->name, 'posted_at' => $movement->posted_at->format('d M Y, H:i'), 'posted_by' => $movement->postedBy->name];
                    })->filter(fn (array $movement): bool => (float) $movement['quantity'] > 0)->values()->all(),
                    'can_manage' => Gate::forUser($user)->allows('manage', $line),
                    'can_direct_issue' => Gate::forUser($user)->allows('directIssue', $line),
                    'can_mark_external' => Gate::forUser($user)->allows('markExternal', $line),
                ];
            })->values()->all() : [],
            'canViewCosts' => $canViewCosts,
            'reviews' => $dailySiteReport->reviews
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (DailySiteReportReview $review): array => [
                    'id' => $review->id,
                    'action' => $review->action,
                    'remarks' => $review->remarks,
                    'reviewed_by' => $review->reviewer?->name,
                    'created_at' => $review->created_at->toDateTimeString(),
                ]),
            'corrections' => $dailySiteReport->corrections
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (DailySiteReportCorrection $correction): array => [
                    'id' => $correction->id,
                    'status' => $correction->status,
                    'reason' => $correction->reason,
                    'old_values' => $correction->old_values,
                    'new_values' => $correction->new_values,
                    'requested_by' => $correction->requester?->name,
                    'created_at' => $correction->created_at->toDateTimeString(),
                    'can_manage' => Gate::forUser($user)->allows('approveCorrection', [$dailySiteReport, $correction]),
                ]),
            'documents' => $linkedDocuments,
            'canUploadDocuments' => Gate::forUser($user)->allows('create', Document::class),
            ...$this->formOptions($user),
            ...$this->documentFormOptions($user),
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
        Gate::authorize('archive', $dailySiteReport);

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
                'name' => sprintf('%s (%s)', $site->name, $site->project?->reference),
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
            'equipmentOptions' => Equipment::query()
                ->with(['branch', 'category'])
                ->where('tenant_id', $tenantId)
                ->whereIn('branch_id', $branchIds)
                ->where('is_active', true)
                ->visibleTo($user)
                ->orderBy('asset_code')
                ->get()
                ->map(fn (Equipment $equipment): array => [
                    'id' => $equipment->id,
                    'branch_id' => $equipment->branch_id,
                    'name' => $equipment->name,
                    'asset_code' => $equipment->asset_code,
                    'category_name' => $equipment->category->name,
                    'current_site_id' => $equipment->current_site_id,
                    'current_meter_reading' => $equipment->current_meter_reading,
                    'meter_type' => $equipment->meter_type,
                ]),
            'inventoryItems' => InventoryItem::query()->where('is_active', true)->with(['stockUnit', 'conversions.fromUnit', 'storeSettings', 'batches'])->orderBy('name')->get()->map(fn (InventoryItem $item): array => [
                'id' => $item->id, 'name' => $item->name, 'code' => $item->code, 'stock_unit_id' => $item->stock_unit_id,
                'stock_unit' => $item->stockUnit->symbol ?? $item->stockUnit->name,
                'tracking_type' => $item->tracking_type->value,
                'store_ids' => $item->storeSettings->where('is_active', true)->pluck('inventory_store_id')->values()->all(),
                'units' => collect([['id' => $item->stockUnit->id, 'name' => $item->stockUnit->name, 'symbol' => $item->stockUnit->symbol]])->merge($item->conversions->where('is_active', true)->map(fn (InventoryUnitConversion $conversion): array => ['id' => $conversion->fromUnit->id, 'name' => $conversion->fromUnit->name, 'symbol' => $conversion->fromUnit->symbol]))->unique('id')->values()->all(),
                'batches' => $item->batches->where('is_active', true)->map(fn (InventoryBatch $batch): array => ['id' => $batch->id, 'batch_number' => $batch->batch_number, 'inventory_store_id' => $batch->inventory_store_id])->values()->all(),
            ]),
            'inventoryStores' => InventoryStore::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->with('branch')->orderBy('name')->get()->map(fn (InventoryStore $store): array => ['id' => $store->id, 'branch_id' => $store->branch_id, 'name' => $store->name, 'branch_name' => $store->branch->name]),
            'currencies' => TenantCurrency::query()
                ->with('currency')
                ->where('tenant_id', $tenantId)
                ->where('is_enabled', true)
                ->orderBy('currency_code')
                ->get()
                ->map(fn (TenantCurrency $currency): array => ['id' => $currency->currency_code, 'name' => sprintf('%s - %s', $currency->currency_code, $currency->currency->name)]),
            'units' => [
                'No.',
                'day',
                'hour',
                'kg',
                'litre',
                'lot',
                'm',
                'm2',
                'm3',
                'month',
                'tonne',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(DailySiteReport $report, bool $canViewCosts): array
    {
        return [
            'id' => $report->id,
            'reference' => $report->reference,
            'project_name' => $report->project?->name,
            'site_name' => $report->site?->name,
            'report_date' => $report->report_date->toDateString(),
            'status' => $report->status,
            'output_value' => $report->output_value,
            'input_cost' => $canViewCosts ? $report->input_cost : null,
            'profit_loss' => $canViewCosts ? $report->profit_loss : null,
            'submitted_by' => $report->submittedBy?->name,
            'approved_by' => $report->approvedBy?->name,
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
     * @return list<array<string, mixed>>
     */
    private function workLineRows(DailySiteReport $report, bool $canViewCosts): array
    {
        return collect($this->lineRows($report->workLines->values()->all(), $canViewCosts))
            ->map(function (array $line) use ($report): array {
                $activityId = $line['project_activity_id'] ?? null;
                $previous = 0.0;

                if (is_string($activityId) && $activityId !== '') {
                    $previous = (float) DailySiteReportWorkLine::query()
                        ->where('tenant_id', $report->tenant_id)
                        ->where('project_activity_id', $activityId)
                        ->whereHas('report', fn (Builder $query) => $query
                            ->where('status', DailySiteReport::STATUS_APPROVED)
                            ->whereDate('report_date', '<', $report->report_date->toDateString()))
                        ->sum('quantity');
                }

                $today = is_numeric($line['quantity'] ?? null) ? (float) $line['quantity'] : 0.0;

                return [
                    ...$line,
                    'previous_approved_quantity' => (string) $previous,
                    'cumulative_to_date' => (string) ($previous + $today),
                ];
            })
            ->all();
    }

    /**
     * @param  list<object>  $lines
     * @return list<array<string, mixed>>
     */
    private function lineRows(array $lines, bool $canViewCosts): array
    {
        return collect($lines)
            ->map(function (object $line) use ($canViewCosts): array {
                /** @var array<string, mixed> $row */
                $row = method_exists($line, 'toArray') ? $line->toArray() : (array) $line;

                if (! $canViewCosts) {
                    unset($row['rate_amount'], $row['amount'], $row['currency_code']);
                }

                return $row;
            })
            ->values()
            ->all();
    }
}
