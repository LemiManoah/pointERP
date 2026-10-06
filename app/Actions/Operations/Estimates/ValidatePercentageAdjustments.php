<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Enums\BoqItemType;
use Illuminate\Validation\ValidationException;

final readonly class ValidatePercentageAdjustments
{
    /** @param list<array<string, mixed>> $lines */
    public function handle(array $lines): void
    {
        $byKey = collect($lines)->keyBy('work_item_key');
        foreach ($lines as $index => $line) {
            if (($line['item_type'] ?? null) !== BoqItemType::PercentageAdjustment->value) {
                continue;
            }
            $keys = $line['percentage_base_keys'] ?? [];
            if (! is_array($keys) || $keys === [] || count(array_unique($keys)) !== count($keys)) {
                throw ValidationException::withMessages(['lines.'.$index.'.percentage_base_keys' => 'Select distinct BOQ items as the calculation base.']);
            }
            foreach ($keys as $key) {
                $base = $byKey->get($key);
                if (! $base || $key === ($line['work_item_key'] ?? null) || ($base['item_type'] ?? null) === BoqItemType::PercentageAdjustment->value) {
                    throw ValidationException::withMessages(['lines.'.$index.'.percentage_base_keys' => 'Each base item must belong to this BOQ revision and cannot be a percentage adjustment. Restore removed items or update the calculation base.']);
                }
            }
            $rate = $line['percentage_rate'] ?? null;
            if ($rate !== null && $rate !== '' && (! is_numeric($rate) || ! is_finite((float) $rate) || abs((float) $rate) > 99999999999999)) {
                throw ValidationException::withMessages(['lines.'.$index.'.percentage_rate' => 'Enter a valid percentage.']);
            }
            if ((float) ($line['planned_quantity'] ?? 0) !== 1.0 || ! empty($line['resources']) || ($line['selling_rate'] ?? null) !== null || ($line['estimated_unit_cost'] ?? null) !== null) {
                throw ValidationException::withMessages(['lines.'.$index.'.item_type' => 'Percentage adjustments use quantity 1, a percentage and selected base items. Remove money rates, internal costs and resources.']);
            }
        }
    }
}
