<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransferInventoryItems
{
    public function __construct(private TransferInventoryStock $transferStock) {}

    /** @param array<string, mixed> $data */
    public function handle(InventoryStore $source, InventoryStore $destination, array $data, User $actor): void
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $data['lines'];

        DB::transaction(function () use ($actor, $data, $destination, $lines, $source): void {
            foreach ($lines as $index => $line) {
                $item = InventoryItem::query()->where('is_active', true)->findOrFail($line['inventory_item_id']);
                $enabledStoreCount = InventoryStoreItem::query()
                    ->where('inventory_item_id', $item->id)
                    ->where('is_active', true)
                    ->whereIn('inventory_store_id', [$source->id, $destination->id])
                    ->count();
                if ($enabledStoreCount !== 2) {
                    throw ValidationException::withMessages([sprintf('lines.%d.inventory_item_id', $index) => 'The item must be enabled in both stores before it can be transferred.']);
                }

                try {
                    $this->transferStock->handle($source, $destination, $item, [
                        'original_quantity' => $line['quantity'],
                        'original_unit_id' => $line['unit_of_measure_id'],
                        'inventory_batch_id' => $line['inventory_batch_id'] ?? null,
                        'source_key' => sprintf('store-transfer:%s:%d', $data['transfer_key'], $index),
                        'reason' => $data['reason'],
                    ], $actor);
                } catch (ValidationException $exception) {
                    $message = collect($exception->errors())->flatten()->first();
                    throw ValidationException::withMessages([sprintf('lines.%d.quantity', $index) => is_string($message) ? $message : 'This item could not be transferred.']);
                }
            }
        });
    }
}
