<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditActivity;
use App\Models\Branch;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditTrailController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('audit-trail.view'), 403);

        $tenantId = resolve(TenantContext::class)->id();
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'event' => $request->string('event')->trim()->value(),
            'branch_id' => $request->string('branch_id')->trim()->value(),
            'actor_id' => $request->string('actor_id')->trim()->value(),
            'subject_type' => $request->string('subject_type')->trim()->value(),
        ];

        $activities = AuditActivity::query()
            ->with(['causer', 'subject', 'branch'])
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('event', 'like', '%'.$filters['search'].'%')
                    ->orWhere('description', 'like', '%'.$filters['search'].'%')
                    ->orWhere('subject_type', 'like', '%'.$filters['search'].'%')
                    ->orWhere('causer_type', 'like', '%'.$filters['search'].'%')))
            ->when($filters['event'] !== '', fn (Builder $query): Builder => $query->where('event', $filters['event']))
            ->when($filters['branch_id'] !== '', fn (Builder $query): Builder => $query->where('branch_id', $filters['branch_id']))
            ->when($filters['actor_id'] !== '', fn (Builder $query): Builder => $query->where('causer_id', $filters['actor_id']))
            ->when($filters['subject_type'] !== '', fn (Builder $query): Builder => $query->where('subject_type', $filters['subject_type']))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AuditActivity $activity): array => [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'tenant_id' => $activity->tenant_id,
                'branch_id' => $activity->branch_id,
                'branch_name' => $activity->branch?->name,
                'subject_type' => $activity->subject_type,
                'subject_label' => $this->modelLabel($activity->subject_type),
                'subject_id' => $activity->subject_id,
                'actor_id' => $activity->causer_id,
                'actor_name' => $activity->causer instanceof User ? $activity->causer->name : null,
                'actor_email' => $activity->causer instanceof User ? $activity->causer->email : null,
                'changes' => $activity->attribute_changes?->toArray() ?? [],
                'reason' => $activity->reason,
                'ip_address' => $activity->ip_address,
                'user_agent' => $activity->user_agent,
                'created_at' => $activity->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('audit-trail/index', [
            'activities' => $activities,
            'filters' => $filters,
            'events' => AuditActivity::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event')
                ->values(),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ]),
            'actors' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'subjectTypes' => AuditActivity::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->map(fn (string $subjectType): array => [
                    'value' => $subjectType,
                    'label' => $this->modelLabel($subjectType),
                ])
                ->values(),
        ]);
    }

    private function modelLabel(?string $className): string
    {
        if ($className === null || $className === '') {
            return 'System';
        }

        return str($className)->afterLast('\\')->headline()->value();
    }
}
