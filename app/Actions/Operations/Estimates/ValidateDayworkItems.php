<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Enums\BoqItemType;
use App\Enums\UnitDimension;
use App\Models\EquipmentCategory;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\UnitOfMeasure;
use App\Models\WorkforceTrade;
use Illuminate\Validation\ValidationException;

final readonly class ValidateDayworkItems
{
    /** @param list<array<string, mixed>> $lines */
    public function handle(Project $project, array $lines): void
    {
        $usedTargets = [];
        foreach ($lines as $index => $line) {
            if (($line['item_type'] ?? null) !== BoqItemType::Daywork->value) {
                continue;
            }

            $type = $line['daywork_resource_type'] ?? null;
            $targets = array_filter([
                'material' => $line['daywork_inventory_item_id'] ?? null,
                'equipment' => $line['daywork_equipment_category_id'] ?? null,
                'labour' => $line['daywork_workforce_trade_id'] ?? null,
            ]);
            if (! is_string($type) || ! in_array($type, ['material', 'equipment', 'labour'], true)
                || count($targets) !== 1 || ! isset($targets[$type])) {
                throw ValidationException::withMessages(['lines.'.$index.'.daywork_resource_type' => 'Choose one daywork source and its matching material, equipment category or workforce trade.']);
            }

            $unitId = $line['unit_of_measure_id'] ?? null;
            $valid = match ($type) {
                'material' => InventoryItem::query()->where('tenant_id', $project->tenant_id)->where('is_active', true)->whereKey($targets[$type])->where('stock_unit_id', $unitId)->exists(),
                'equipment' => EquipmentCategory::query()->where('tenant_id', $project->tenant_id)->where('is_active', true)->whereKey($targets[$type])->exists()
                    && $this->isTimeUnit($project, $unitId),
                'labour' => WorkforceTrade::query()->where('tenant_id', $project->tenant_id)->where('is_active', true)->whereKey($targets[$type])->exists()
                    && $this->isTimeUnit($project, $unitId),
            };
            if (! $valid) {
                throw ValidationException::withMessages(['lines.'.$index.'.unit_of_measure_id' => $type === 'material'
                    ? 'A material daywork must use the selected inventory item’s stock unit.'
                    : 'Labour and equipment dayworks require a time unit.']);
            }

            $targetKey = $type.':'.$targets[$type];
            if (isset($usedTargets[$targetKey])) {
                throw ValidationException::withMessages(['lines.'.$index.'.daywork_resource_type' => 'Each approved usage source can be mapped to only one daywork BOQ item in a revision.']);
            }

            $usedTargets[$targetKey] = true;
        }
    }

    private function isTimeUnit(Project $project, mixed $unitId): bool
    {
        return UnitOfMeasure::query()->whereKey($unitId)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $project->tenant_id))
            ->where('quantity_dimension', UnitDimension::Time->value)->exists();
    }
}
