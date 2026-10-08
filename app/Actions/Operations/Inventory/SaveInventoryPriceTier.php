<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\InventoryPriceTier;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Str;

final readonly class SaveInventoryPriceTier
{
    public function __construct(private TenantContext $tenantContext, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?InventoryPriceTier $tier = null): InventoryPriceTier
    {
        $tenantId = $this->tenantContext->id();
        $attributes = [
            'tenant_id' => $tenantId,
            'code' => $tier instanceof InventoryPriceTier ? $data['code'] : $this->generateCode($tenantId),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'priority' => $tier instanceof InventoryPriceTier ? $data['priority'] : $this->nextPriority($tenantId),
            'is_active' => $data['is_active'],
            'updated_by' => $actor->id,
        ];
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

    private function generateCode(string $tenantId): string
    {
        do {
            $code = 'PL-'.Str::upper(Str::random(8));
        } while (InventoryPriceTier::withTrashed()->where('tenant_id', $tenantId)->where('code', $code)->exists());

        return $code;
    }

    private function nextPriority(string $tenantId): int
    {
        $lastPriority = InventoryPriceTier::withTrashed()->where('tenant_id', $tenantId)->max('priority');

        return min(($lastPriority === null ? 99 : (int) $lastPriority) + 1, 65535);
    }
}
