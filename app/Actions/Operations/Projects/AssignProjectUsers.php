<?php

declare(strict_types=1);

namespace App\Actions\Operations\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignProjectUsers
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /**
     * @param  list<array{user_id: string, role?: string|null, can_manage?: bool}>  $assignments
     */
    public function handle(Project $project, array $assignments, User $actor): void
    {
        $oldValues = $project->users()->get(['users.id'])->pluck('id')->values()->all();

        DB::transaction(function () use ($actor, $assignments, $oldValues, $project): void {
            $sync = [];

            foreach ($assignments as $assignment) {
                $user = User::query()->whereKey($assignment['user_id'])->firstOrFail();

                if ($user->tenant_id !== $project->tenant_id || (! $user->branches()->whereKey($project->branch_id)->exists() && ! $user->can('branches.view-all'))) {
                    throw ValidationException::withMessages(['users' => 'One or more users cannot access the project branch.']);
                }

                $sync[$user->id] = [
                    'role' => $assignment['role'] ?? null,
                    'can_manage' => (bool) ($assignment['can_manage'] ?? false),
                ];
            }

            $project->users()->sync($sync);

            $this->auditLogger->record('operations.project_users.updated', $project, $actor, [
                'users' => $oldValues,
            ], [
                'users' => array_keys($sync),
            ]);
        });
    }
}
