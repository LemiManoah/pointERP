<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransferInventoryStock
{
    public function __construct(private PostInventoryStockMovement $postMovement) {}

    /** @param array<string, mixed> $data */
    public function handle(InventoryStore $source, InventoryStore $destination, InventoryItem $item, array $data, User $actor): void
    {
        if ($source->id === $destination->id) {
            throw ValidationException::withMessages(['destination_store_id' => 'Choose a different destination store.']);
        }

        DB::transaction(function () use ($actor, $data, $destination, $item, $source): void {
            $base = [...$data, 'source_type' => $data['source_type'] ?? 'store_transfer'];
            $this->postMovement->handle($source, $item, [...$base, 'movement_type' => InventoryMovementType::TransferOut->value, 'source_key' => $data['source_key'].':out'], $actor);
            $this->postMovement->handle($destination, $item, [...$base, 'movement_type' => InventoryMovementType::TransferIn->value, 'source_key' => $data['source_key'].':in'], $actor);
        });
    }
}
