<?php

declare(strict_types=1);

namespace App\Actions\Operations\Sites;

use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignSiteUsers
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /**
     * @param  list<array{user_id: string, role?: string|null, can_submit_dsr?: bool, can_review_dsr?: bool}>  $assignments
     */
    public function handle(Site $site, array $assignments, User $actor): void
    {
        $oldValues = $site->users()->get(['users.id'])->pluck('id')->values()->all();

        DB::transaction(function () use ($actor, $assignments, $oldValues, $site): void {
            $sync = [];

            foreach ($assignments as $assignment) {
                $user = User::query()->whereKey($assignment['user_id'])->firstOrFail();

                if ($user->tenant_id !== $site->tenant_id || (! $user->branches()->whereKey($site->branch_id)->exists() && ! $user->can('branches.view-all'))) {
                    throw ValidationException::withMessages(['users' => 'One or more users cannot access the site branch.']);
                }

                $sync[$user->id] = [
                    'role' => $assignment['role'] ?? null,
                    'can_submit_dsr' => (bool) ($assignment['can_submit_dsr'] ?? false),
                    'can_review_dsr' => (bool) ($assignment['can_review_dsr'] ?? false),
                ];
            }

            $site->users()->sync($sync);

            $this->auditLogger->record('operations.site_users.updated', $site, $actor, [
                'users' => $oldValues,
            ], [
                'users' => array_keys($sync),
            ]);
        });
    }
}
