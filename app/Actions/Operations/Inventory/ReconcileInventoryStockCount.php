<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\InventoryStockBalance;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReconcileInventoryStockCount
{
    public function __construct(private InventoryStockBalance $balances, private PostInventoryStockMovement $postMovement) {}

    /** @param array<string, mixed> $data */
    public function handle(InventoryStore $store, array $data, User $actor): int
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $data['lines'];

        return DB::transaction(function () use ($actor, $data, $lines, $store): int {
            $posted = 0;
            foreach ($lines as $index => $line) {
                if (($line['counted_quantity'] ?? null) === null || $line['counted_quantity'] === '') {
                    continue;
                }

                $item = InventoryItem::query()->where('is_active', true)->findOrFail($line['inventory_item_id']);
                $batchId = $line['inventory_batch_id'] ?? null;
                $sourceKey = sprintf('stock-count:%s:%s:%s', $data['count_key'], $item->id, is_string($batchId) ? $batchId : 'unbatched');
                if (InventoryStockMovement::query()->where('source_key', $sourceKey)->exists()) {
                    continue;
                }

                $current = BigDecimal::of(is_string($batchId) ? $this->balances->forBatch($store, $item, $batchId) : $this->balances->for($store, $item)['on_hand']);
                $snapshot = BigDecimal::of((string) $line['system_quantity']);
                if (! $current->isEqualTo($snapshot)) {
                    throw ValidationException::withMessages([sprintf('lines.%d.counted_quantity', $index) => 'Stock changed after this count page was opened. Refresh and enter the count again.']);
                }

                $difference = BigDecimal::of((string) $line['counted_quantity'])->minus($current);
                if ($difference->isZero()) {
                    continue;
                }

                $this->postMovement->handle($store, $item, [
                    'movement_type' => InventoryMovementType::Adjustment->value,
                    'adjustment_direction' => $difference->isNegative() ? 'decrease' : 'increase',
                    'original_quantity' => (string) $difference->abs(),
                    'original_unit_id' => $item->stock_unit_id,
                    'inventory_batch_id' => $batchId,
                    'source_type' => 'physical_stock_count',
                    'source_key' => $sourceKey,
                    'reason' => $data['reason'],
                ], $actor);
                $posted++;
            }

            if ($posted === 0 && ! collect($lines)->contains(fn (array $line): bool => ($line['counted_quantity'] ?? null) !== null && $line['counted_quantity'] !== '')) {
                throw ValidationException::withMessages(['lines' => 'Enter at least one physical count.']);
            }

            return $posted;
        });
    }
}
