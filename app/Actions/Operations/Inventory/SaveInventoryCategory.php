<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\InventoryCategory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;

final readonly class SaveInventoryCategory
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?InventoryCategory $category = null): InventoryCategory
    {
        $attributes = ['tenant_id' => $this->tenantContext->id(), 'code' => $data['code'], 'name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => $data['is_active'], 'updated_by' => $actor->id];
        $old = $category?->only(array_keys($attributes)) ?? [];
        if ($category instanceof InventoryCategory) {
            $category->update($attributes);
            $event = 'inventory.category.updated';
        } else {
            $category = InventoryCategory::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'inventory.category.created';
        }
        $this->auditLogger->record($event, $category, $actor, $old, $category->fresh()?->toArray() ?? []);

        return $category;
    }
}
