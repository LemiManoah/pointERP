<?php

declare(strict_types=1);

namespace App\Actions\Operations\Sites;

use App\Enums\InventoryStoreType;
use App\Models\InventoryStore;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveSite
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /**
     * @param  array{project_id: string, reference: string, name: string, location_name?: string|null, latitude?: string|null, longitude?: string|null, manager_id?: string|null, reporting_deadline?: string|null, status: string}  $data
     */
    public function handle(array $data, User $actor, ?Site $site = null): Site
    {
        return DB::transaction(function () use ($actor, $data, $site): Site {
            $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

            if (($data['manager_id'] ?? null) !== null) {
                $manager = User::query()->whereKey($data['manager_id'])->firstOrFail();

                if (! $manager->branches()->whereKey($project->branch_id)->exists() && ! $manager->can('branches.view-all')) {
                    throw ValidationException::withMessages(['manager_id' => 'The selected manager does not have access to the project branch.']);
                }
            }

            $attributes = [
                'tenant_id' => $project->tenant_id,
                'branch_id' => $project->branch_id,
                'project_id' => $project->id,
                'reference' => Str::upper($data['reference']),
                'name' => $data['name'],
                'location_name' => $data['location_name'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'reporting_deadline' => $data['reporting_deadline'] ?? null,
                'status' => $data['status'],
                'updated_by' => $actor->id,
            ];

            $oldValues = $site instanceof Site ? $site->only(array_keys($attributes)) : [];

            if ($site instanceof Site) {
                $site->update($attributes);
                $event = 'operations.site.updated';
            } else {
                $site = Site::query()->create([...$attributes, 'created_by' => $actor->id]);
                $event = 'operations.site.created';
            }

            $this->ensureDefaultStore($site, $actor);

            if (($data['manager_id'] ?? null) !== null) {
                $site->users()->syncWithoutDetaching([
                    (string) $data['manager_id'] => ['role' => 'Site Manager', 'can_submit_dsr' => true, 'can_review_dsr' => false],
                ]);
            }

            $this->auditLogger->record($event, $site, $actor, $oldValues, $site->fresh()?->toArray() ?? []);

            return $site;
        });
    }

    private function ensureDefaultStore(Site $site, User $actor): void
    {
        if (InventoryStore::query()->where('site_id', $site->id)->where('is_default_for_site', true)->exists()) {
            return;
        }

        $baseCode = Str::upper(Str::slug($site->reference, '-')).'-STORE';
        $code = $baseCode;
        $suffix = 2;

        while (InventoryStore::query()->where('tenant_id', $site->tenant_id)->where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        InventoryStore::query()->create([
            'tenant_id' => $site->tenant_id,
            'branch_id' => $site->branch_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'is_default_for_site' => true,
            'code' => $code,
            'name' => $site->name.' Store',
            'type' => InventoryStoreType::SiteStore,
            'address' => $site->location_name,
            'is_active' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
