<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\InventoryPriceTier;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;

final readonly class SaveInventoryPriceTier
{
    public function __construct(private TenantContext $tenantContext, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?InventoryPriceTier $tier = null): InventoryPriceTier
    {
        $attributes = ['tenant_id' => $this->tenantContext->id(), 'code' => $data['code'], 'name' => $data['name'], 'description' => $data['description'] ?? null, 'priority' => $data['priority'], 'is_active' => $data['is_active'], 'updated_by' => $actor->id];
        $old = $tier?->only(array_keys($attributes)) ?? [];
        if ($tier instanceof InventoryPriceTier) {
            $tier->update($attributes);
            $event = 'inventory.price_list.updated';
        } else {
            $tier = InventoryPriceTier::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'inventory.price_list.created';
        }

        $this->auditLogger->record($event, $tier, $actor, $old, $tier->fresh()?->toArray() ?? []);

        return $tier;
    }
}
