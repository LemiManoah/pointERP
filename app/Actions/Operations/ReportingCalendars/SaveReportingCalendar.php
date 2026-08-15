<?php

declare(strict_types=1);

namespace App\Actions\Operations\ReportingCalendars;

use App\Models\Project;
use App\Models\ReportingCalendar;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SaveReportingCalendar
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuditLogger $auditLogger,
    ) {
        //
    }

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?ReportingCalendar $calendar = null): ReportingCalendar
    {
        $project = isset($data['project_id']) && is_string($data['project_id'])
            ? Project::query()->find($data['project_id'])
            : null;
        $site = isset($data['site_id']) && is_string($data['site_id'])
            ? Site::query()->find($data['site_id'])
            : null;

        if ($site instanceof Site) {
            $project = $site->project;
        }

        if ($site instanceof Site) {
            Gate::forUser($actor)->authorize('view', $site);
        } elseif ($project instanceof Project) {
            Gate::forUser($actor)->authorize('view', $project);
        } else {
            abort_unless($actor->can('branches.view-all'), 403, 'Only all-branch users can manage the tenant reporting calendar.');
        }

        $projectId = $project?->id;
        $siteId = $site?->id;
        $branchId = $site instanceof Site ? $site->branch_id : $project?->branch_id;
        $isActive = (bool) $data['is_active'];

        if ($isActive && $this->activeScopeExists($projectId, $siteId, $calendar?->id)) {
            throw ValidationException::withMessages([
                'scope' => 'An active reporting calendar already exists for this scope.',
            ]);
        }

        $calendar ??= new ReportingCalendar();
        $oldValues = $calendar->exists ? $calendar->toArray() : [];
        $calendar->fill([
            ...$data,
            'tenant_id' => $this->tenantContext->id(),
            'branch_id' => $branchId,
            'project_id' => $projectId,
            'site_id' => $siteId,
            'created_by' => $calendar->exists ? $calendar->created_by : $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        $this->auditLogger->record(
            $oldValues === [] ? 'operations.reporting_calendar.created' : 'operations.reporting_calendar.updated',
            $calendar,
            $actor,
            $oldValues,
            $calendar->fresh()?->toArray() ?? [],
        );

        return $calendar;
    }

    private function activeScopeExists(?string $projectId, ?string $siteId, ?string $ignoreId): bool
    {
        return ReportingCalendar::query()
            ->where('is_active', true)
            ->when($ignoreId, fn (Builder $query, string $id) => $query->whereKeyNot($id))
            ->when($projectId, fn (Builder $query, string $id) => $query->where('project_id', $id), fn (Builder $query) => $query->whereNull('project_id'))
            ->when($siteId, fn (Builder $query, string $id) => $query->where('site_id', $id), fn (Builder $query) => $query->whereNull('site_id'))
            ->exists();
    }
}
