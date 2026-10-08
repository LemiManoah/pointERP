<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Operations\Boq\ReconcileProjectProgress;
use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

#[Signature('boq:reconcile {project : Project UUID} {--user= : Existing active user email}')]
#[Description('Read-only comparison of approved DSR output, BOQ progress and saved activity totals')]
final class ReconcileBoqProgress extends Command
{
    public function handle(TenantContext $context, ReconcileProjectProgress $reconcile): int
    {
        if (! $this->option('user')) {
            $this->error('Provide --user with an existing active user email.');

            return self::FAILURE;
        }
        $actor = User::query()->where('email', $this->option('user'))->where('is_active', true)->firstOrFail();
        $previous = $context->has() ? $context->current() : null;
        try {
            $context->set($actor->tenant);
            $project = Project::query()->whereKey($this->argument('project'))->firstOrFail();
            Gate::forUser($actor)->authorize('view', $project);
            Gate::forUser($actor)->authorize('viewAny', ProjectEstimate::class);
            $findings = $reconcile->handle($project);
            $this->info('BOQ reconciliation: '.$project->reference.' — '.$project->name);
            if ($findings === []) {
                $this->info('No quantity or source-link differences found. Physical overlap still requires reviewer verification.');

                return self::SUCCESS;
            }
            $this->table(['Issue', 'Record ID', 'Detail'], $findings);
            $this->warn(count($findings).' finding(s) require review. No records were changed.');

            return self::FAILURE;
        } finally {
            if ($previous) {
                $context->set($previous);
            } else {
                $context->forget();
            }
        }
    }
}
