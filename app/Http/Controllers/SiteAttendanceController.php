<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Workforce\SaveSiteAttendance;
use App\Enums\AttendanceShift;
use App\Enums\AttendanceStatus;
use App\Enums\DsrLabourSource;
use App\Http\Requests\Operations\Workforce\SaveSiteAttendanceRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteAttendanceRecord;
use App\Models\SiteAttendanceRegister;
use App\Models\Staff;
use App\Models\StaffDeployment;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SiteAttendanceController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SiteAttendanceRegister::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $branchIds = $branchContext->accessibleBranchIds($actor);

        $registers = SiteAttendanceRegister::query()
            ->with(['confirmer', 'project', 'recorder', 'records', 'site'])
            ->where('tenant_id', $tenantId)
            ->unless($branchContext->canViewAllBranches($actor), fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->latest('attendance_date')
            ->latest('created_at')
            ->get()
            ->filter(fn (SiteAttendanceRegister $register): bool => Gate::forUser($actor)->allows('view', $register))
            ->values();

        return Inertia::render('operations/workforce/attendance/index', [
            'registers' => $registers->map(fn (SiteAttendanceRegister $register): array => $this->registerSummary($register)),
            'canCreate' => Gate::allows('create', SiteAttendanceRegister::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', SiteAttendanceRegister::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return Inertia::render('operations/workforce/attendance/create', $this->formOptions($actor));
    }

    public function store(SaveSiteAttendanceRequest $request, SaveSiteAttendance $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $site = Site::query()->findOrFail((string) $request->validated('site_id'));
        Gate::authorize('create', [SiteAttendanceRegister::class, $site]);

        /** @var array{project_id: string, site_id: string, attendance_date: string, shift: string, notes?: string|null, records: list<array{labour_source: string, staff_id?: string|null, subcontractor_id?: string|null, workforce_trade_id: string, worker_name_snapshot?: string|null, headcount: int, attendance_status: string, regular_hours_per_person: numeric-string|int|float, overtime_hours_per_person: numeric-string|int|float, notes?: string|null}>} $data */
        $data = $request->validated();
        $register = $action->handle($data, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance draft saved.']);

        return to_route('workforce.attendance.show', $register);
    }

    public function show(Request $request, SiteAttendanceRegister $siteAttendanceRegister): Response
    {
        Gate::authorize('view', $siteAttendanceRegister);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $siteAttendanceRegister->load(['branch', 'confirmer', 'project', 'recorder', 'records.staff', 'records.subcontractor', 'records.trade', 'reopenedBy', 'site']);

        return Inertia::render('operations/workforce/attendance/show', [
            'register' => [
                ...$this->registerSummary($siteAttendanceRegister),
                'project_id' => $siteAttendanceRegister->project_id,
                'site_id' => $siteAttendanceRegister->site_id,
                'notes' => $siteAttendanceRegister->notes,
                'reopen_reason' => $siteAttendanceRegister->reopen_reason,
                'reopened_by_name' => $siteAttendanceRegister->reopenedBy?->name,
                'records' => $siteAttendanceRegister->records->map(fn (SiteAttendanceRecord $record): array => [
                    'id' => $record->id,
                    'labour_source' => $record->labour_source->value,
                    'staff_id' => $record->staff_id,
                    'subcontractor_id' => $record->subcontractor_id,
                    'workforce_trade_id' => $record->workforce_trade_id,
                    'worker_name' => $record->worker_name_snapshot,
                    'subcontractor_name' => $record->subcontractor_name_snapshot,
                    'trade_name' => $record->trade->name,
                    'headcount' => $record->headcount,
                    'attendance_status' => $record->attendance_status->value,
                    'attendance_status_label' => $record->attendance_status->label(),
                    'regular_hours_per_person' => $record->regular_hours_per_person,
                    'overtime_hours_per_person' => $record->overtime_hours_per_person,
                    'person_hours' => $record->personHours(),
                    'notes' => $record->notes,
                ])->values()->all(),
            ],
            ...$this->formOptions($actor),
            'can' => [
                'update' => Gate::allows('update', $siteAttendanceRegister),
                'confirm' => Gate::allows('confirm', $siteAttendanceRegister),
                'reopen' => Gate::allows('reopen', $siteAttendanceRegister),
            ],
        ]);
    }

    public function update(SaveSiteAttendanceRequest $request, SiteAttendanceRegister $siteAttendanceRegister, SaveSiteAttendance $action): RedirectResponse
    {
        Gate::authorize('update', $siteAttendanceRegister);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{project_id: string, site_id: string, attendance_date: string, shift: string, notes?: string|null, records: list<array{labour_source: string, staff_id?: string|null, subcontractor_id?: string|null, workforce_trade_id: string, worker_name_snapshot?: string|null, headcount: int, attendance_status: string, regular_hours_per_person: numeric-string|int|float, overtime_hours_per_person: numeric-string|int|float, notes?: string|null}>} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $siteAttendanceRegister);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance draft updated.']);

        return to_route('workforce.attendance.show', $siteAttendanceRegister);
    }

    /** @return array<string, mixed> */
    private function formOptions(User $actor): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $branchIds = $branchContext->accessibleBranchIds($actor);
        $viewAll = $branchContext->canViewAllBranches($actor);

        $projects = Project::query()
            ->with(['branch', 'sites' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->unless($viewAll, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $project): bool => Gate::forUser($actor)->allows('view', $project))
            ->values();

        return [
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'reference' => $project->reference,
                'branch_id' => $project->branch_id,
                'sites' => $project->sites
                    ->filter(fn (Site $site): bool => Gate::forUser($actor)->allows('view', $site))
                    ->map(fn (Site $site): array => ['id' => $site->id, 'name' => $site->name])
                    ->values()
                    ->all(),
            ]),
            'staff' => Staff::query()
                ->with('primaryTrade')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->unless($viewAll, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
                ->orderBy('name')
                ->get()
                ->map(fn (Staff $staff): array => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'staff_number' => $staff->staff_number,
                    'branch_id' => $staff->branch_id,
                    'employment_type' => $staff->employment_type->value,
                    'primary_trade_id' => $staff->primary_trade_id,
                ]),
            'deployments' => StaffDeployment::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->unless($viewAll, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
                ->get()
                ->map(fn (StaffDeployment $deployment): array => [
                    'staff_id' => $deployment->staff_id,
                    'project_id' => $deployment->project_id,
                    'site_id' => $deployment->site_id,
                    'workforce_trade_id' => $deployment->workforce_trade_id,
                    'starts_on' => $deployment->starts_on->toDateString(),
                    'ends_on' => $deployment->ends_on?->toDateString(),
                ]),
            'trades' => WorkforceTrade::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'subcontractors' => Customer::query()->where('tenant_id', $tenantId)->where('type', Customer::TYPE_SUBCONTRACTOR)->where('status', 'active')->where(function (Builder $query) use ($branchIds): void {
                $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })->orderBy('name')->get(['id', 'name', 'branch_id']),
            'shifts' => collect(AttendanceShift::cases())->map(fn (AttendanceShift $shift): array => ['value' => $shift->value, 'label' => $shift->label()]),
            'attendanceStatuses' => collect(AttendanceStatus::cases())->map(fn (AttendanceStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'labourSources' => collect(DsrLabourSource::cases())->map(fn (DsrLabourSource $source): array => ['value' => $source->value, 'label' => $source->label()]),
        ];
    }

    /** @return array<string, mixed> */
    private function registerSummary(SiteAttendanceRegister $register): array
    {
        return [
            'id' => $register->id,
            'project_name' => $register->project->name,
            'project_reference' => $register->project->reference,
            'site_name' => $register->site->name,
            'attendance_date' => $register->attendance_date->toDateString(),
            'shift' => $register->shift->value,
            'shift_label' => $register->shift->label(),
            'status' => $register->status->value,
            'status_label' => $register->status->label(),
            'line_count' => $register->records->count(),
            'headcount' => (int) $register->records->sum('headcount'),
            'person_hours' => (float) $register->records->sum(fn (SiteAttendanceRecord $record): float => $record->personHours()),
            'recorded_by_name' => $register->recorder->name,
            'confirmed_by_name' => $register->confirmer?->name,
            'confirmed_at' => $register->confirmed_at?->toIso8601String(),
        ];
    }
}
