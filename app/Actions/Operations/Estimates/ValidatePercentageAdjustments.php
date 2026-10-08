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
        $checked = [];
        foreach ($lines as $index => $line) {
            if (($line['item_type'] ?? null) !== BoqItemType::PercentageAdjustment->value) {
                continue;
            }

            $keys = $line['percentage_base_keys'] ?? [];
            if (! is_string($line['work_item_key'] ?? null) || $line['work_item_key'] === '') {
                throw ValidationException::withMessages(['lines.'.$index.'.work_item_key' => 'A percentage adjustment requires an item reference before choosing its base.']);
            }

            if (! is_array($keys) || $keys === [] || count(array_unique($keys)) !== count($keys)) {
                throw ValidationException::withMessages(['lines.'.$index.'.percentage_base_keys' => 'Select distinct BOQ items as the calculation base.']);
            }

            foreach ($keys as $key) {
                $base = $byKey->get($key);
                if (! $base || $key === ($line['work_item_key'] ?? null)) {
                    throw ValidationException::withMessages(['lines.'.$index.'.percentage_base_keys' => 'Select other items in this BOQ revision as the calculation base.']);
                }
            }

            $visit = function (string $key, array $path) use (&$visit, &$checked, $byKey, $index): void {
                if (isset($path[$key]) || count($path) >= 100) {
                    throw ValidationException::withMessages(['lines.'.$index.'.percentage_base_keys' => 'Percentage bases must not contain a circular dependency or more than 100 levels.']);
                }

                if (isset($checked[$key])) {
                    return;
                }

                $path[$key] = true;
                $base = $byKey->get($key);
                foreach (($base['percentage_base_keys'] ?? []) as $dependency) {
                    $visit($dependency, $path);
                }

                $checked[$key] = true;
            };
            $visit($line['work_item_key'], []);

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
