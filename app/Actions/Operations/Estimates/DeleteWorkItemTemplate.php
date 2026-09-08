<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Models\User;
use App\Models\WorkItemTemplate;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final readonly class DeleteWorkItemTemplate
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(WorkItemTemplate $template, User $actor): void
    {
        DB::transaction(function () use ($actor, $template): void {
            $oldValues = $template->load('resources')->toArray();

            $template->delete();

            $this->auditLogger->record(
                'operations.work_item_template.deleted',
                $template,
                $actor,
                $oldValues,
                [],
            );
        });
    }
}
