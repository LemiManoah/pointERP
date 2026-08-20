<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryUnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Validation\ValidationException;

final readonly class SaveInventoryUnitConversion
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, InventoryItem $item, User $actor, ?InventoryUnitConversion $conversion = null): InventoryUnitConversion
    {
        $fromUnit = UnitOfMeasure::query()->findOrFail($data['from_unit_id']);
        $stockUnit = $item->stockUnit()->firstOrFail();
        if ($fromUnit->id === $stockUnit->id) {
            throw ValidationException::withMessages(['from_unit_id' => 'Choose a unit different from the stock unit.']);
        }

        $conversion ??= InventoryUnitConversion::query()
            ->where('inventory_item_id', $item->id)
            ->where('from_unit_id', $fromUnit->id)
            ->where('to_unit_id', $stockUnit->id)
            ->first();
        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'inventory_item_id' => $item->id,
            'from_unit_id' => $fromUnit->id,
            'to_unit_id' => $stockUnit->id,
            'multiplier' => $data['multiplier'],
            'divisor' => 1,
            'effective_from' => $data['effective_from'] ?? null,
            'reason' => $data['reason'] ?? null,
            'is_active' => $data['is_active'],
        ];
        $old = $conversion?->only(array_keys($attributes)) ?? [];
        if ($conversion instanceof InventoryUnitConversion) {
            $conversion->update($attributes);
            $event = 'inventory.unit_conversion.updated';
        } else {
            $conversion = InventoryUnitConversion::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'inventory.unit_conversion.created';
        }

        $this->auditLogger->record($event, $conversion, $actor, $old, $conversion->fresh()?->toArray() ?? []);

        return $conversion;
    }
}
