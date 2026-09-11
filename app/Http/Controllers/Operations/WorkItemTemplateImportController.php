<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Estimates\ImportWorkItemTemplates;
use App\Http\Requests\Operations\Estimates\ImportWorkItemTemplatesRequest;
use App\Models\User;
use App\Models\WorkItemTemplate;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class WorkItemTemplateImportController
{
    public function __invoke(ImportWorkItemTemplatesRequest $request, ImportWorkItemTemplates $action): RedirectResponse
    {
        Gate::authorize('create', WorkItemTemplate::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $file = $request->file('file');
        abort_unless($file !== null, 422, 'No file uploaded.');

        $result = $action->handle(resolve(TenantContext::class)->current(), $file, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Imported %d work activities with %d resource norms.', $result['imported_templates'], $result['imported_resources']),
        ]);

        return to_route('work-item-templates.index');
    }
}
