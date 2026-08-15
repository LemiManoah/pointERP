<?php

declare(strict_types=1);

namespace App\Actions\Operations\Documents\Concerns;

use App\Models\Contract;
use App\Models\DailySiteReport;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait ResolvesDocumentLinks
{
    /**
     * @return array<string, class-string<Model>>
     */
    private function allowedLinkTypes(): array
    {
        return [
            'contract' => Contract::class,
            'project' => Project::class,
            'site' => Site::class,
            'daily_site_report' => DailySiteReport::class,
            'equipment' => Equipment::class,
        ];
    }

    private function resolveLinkTarget(string $type, string $id, User $actor): Model
    {
        $modelClass = $this->allowedLinkTypes()[$type] ?? null;

        if ($modelClass === null) {
            throw ValidationException::withMessages(['links' => 'Unsupported document link type.']);
        }

        $target = $modelClass::query()->whereKey($id)->firstOrFail();
        $tenantId = $target->getAttribute('tenant_id');

        if ($tenantId !== $actor->tenant_id || Gate::forUser($actor)->denies('view', $target)) {
            throw ValidationException::withMessages(['links' => 'One or more linked records are outside your access scope.']);
        }

        return $target;
    }
}
