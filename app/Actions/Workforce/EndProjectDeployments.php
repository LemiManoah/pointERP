<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\StaffDeployment;
use App\Models\User;

final readonly class EndProjectDeployments
{
    public function __construct(private EndStaffDeployment $endDeployment) {}
    /**
     * Execute the action.
     */
    public function handle(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor): void {
            foreach (StaffDeployment::query()->where('project_id', $project->id)->where('status', 'active')->lockForUpdate()->get() as $deployment) {
                $this->endDeployment->handle($deployment, $actor);
            }
        });
    }
}
