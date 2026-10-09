<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryStockBalance;
use App\Services\OperationalNotificationSender;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/** @phpstan-type AlertResult array{low_stock: int, recovered: int, overdue_orders: int, material_usage_attention: int, notifications: int} */
final readonly class ProcessInventoryAlerts
{
    public function __construct(
        private TenantContext $tenantContext,
        private InventoryStockBalance $balances,
        private OperationalNotificationSender $notifications,
    ) {}

    /** @return AlertResult */
    public function handle(CarbonImmutable $asOf, ?string $tenantId = null): array
    {
        $result = ['low_stock' => 0, 'recovered' => 0, 'overdue_orders' => 0, 'material_usage_attention' => 0, 'notifications' => 0];
        $tenants = Tenant::query()->active()->when($tenantId, fn (Builder $query, string $id): Builder => $query->whereKey($id))->get();

        foreach ($tenants as $tenant) {
            $this->tenantContext->set($tenant);
            $users = User::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get();
            $this->processStock($users, $asOf, $result);
            $this->processPurchaseOrders($users, $asOf, $result);
        }

        return $result;
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  AlertResult  $result
     */
    private function processStock(Collection $users, CarbonImmutable $asOf, array &$result): void
    {
        $stores = InventoryStore::query()->where('is_active', true)->with('branch')->get();
        $items = InventoryItem::query()->where('is_active', true)->whereNotNull('minimum_stock')->with('stockUnit')->get();
        $balanceMap = $this->balances->forStoresAndItems($stores->pluck('id')->all(), $items->pluck('id')->all());

        foreach ($stores as $store) {
            foreach ($items as $item) {
                $minimum = $item->minimum_stock;
                $available = $balanceMap[$store->id.':'.$item->id]['available'];
                $isLow = BigDecimal::of($available)->isLessThanOrEqualTo((string) $minimum);
                $key = 'inventory-stock:'.$store->id.':'.$item->id;
                $recipients = $this->recipients($users, 'inventory.stock.view', $store->branch_id);
                $latestState = $this->latestState($recipients, $key);

                if (! $isLow && $latestState !== 'low') {
                    continue;
                }

                $state = $isLow ? 'low' : 'recovered';
                if ($this->recentlySent($recipients, $key, $state, $asOf, $isLow ? 7 : 30)) {
                    continue;
                }

                $this->notifications->send($recipients, [
                    'tenant_id' => $item->tenant_id,
                    'branch_id' => $store->branch_id,
                    'inventory_item_id' => $item->id,
                    'inventory_store_id' => $store->id,
                    'alert_key' => $key,
                    'alert_state' => $state,
                    'category' => 'inventory_stock',
                    'severity' => $isLow ? 'warning' : 'success',
                    'title' => $isLow ? 'Inventory item is low in stock' : 'Inventory stock level recovered',
                    'message' => sprintf('%s at %s has %s %s available (minimum %s).', $item->name, $store->name, $available, $item->stockUnit->symbol ?? $item->stockUnit->name, $minimum),
                    'action_url' => '/inventory/items/'.$item->id.'?tab=stock',
                ]);
                $result[$isLow ? 'low_stock' : 'recovered']++;
                $result['notifications'] += $recipients->count();
            }
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  AlertResult  $result
     */
    private function processPurchaseOrders(Collection $users, CarbonImmutable $asOf, array &$result): void
    {
        $orders = PurchaseOrder::query()->whereIn('status', [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived])
            ->whereDate('expected_date', '<', $asOf->toDateString())->with('lines')->get()
            ->filter(fn (PurchaseOrder $order): bool => $order->lines->contains(fn (PurchaseOrderLine $line): bool => BigDecimal::of($line->outstandingQuantity())->isGreaterThan(0)));

        foreach ($orders as $order) {
            $key = 'inventory-po-overdue:'.$order->id;
            $recipients = $this->recipients($users, 'inventory.purchase-orders.view', $order->branch_id);
            if ($this->recentlySent($recipients, $key, 'overdue', $asOf, 7)) {
                continue;
            }

            $this->notifications->send($recipients, [
                'tenant_id' => $order->tenant_id, 'branch_id' => $order->branch_id, 'purchase_order_id' => $order->id,
                'alert_key' => $key, 'alert_state' => 'overdue', 'category' => 'inventory_procurement', 'severity' => 'warning',
                'title' => 'Purchase order delivery is overdue',
                'message' => sprintf('%s from %s was expected on %s and still has outstanding items.', $order->order_number, $order->supplier_name_snapshot, $order->expected_date?->toDateString()),
                'action_url' => '/inventory/purchase-orders/'.$order->id,
            ]);
            $result['overdue_orders']++;
            $result['notifications'] += $recipients->count();
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function recipients(Collection $users, string $permission, string $branchId): Collection
    {
        return $users->filter(fn (User $user): bool => $user->can($permission)
            && ($user->can('branches.view-all') || $user->branches()->whereKey($branchId)->exists()))->values();
    }

    /** @param Collection<int, User> $recipients */
    private function recentlySent(Collection $recipients, string $key, string $state, CarbonImmutable $asOf, int $days): bool
    {
        if ($recipients->isEmpty()) {
            return true;
        }

        return $recipients->every(fn (User $user): bool => $user->notifications()->where('created_at', '>=', $asOf->subDays($days))->get()
            ->contains(fn (DatabaseNotification $notification): bool => ($notification->data['alert_key'] ?? null) === $key && ($notification->data['alert_state'] ?? null) === $state));
    }

    /** @param Collection<int, User> $recipients */
    private function latestState(Collection $recipients, string $key): ?string
    {
        foreach ($recipients as $user) {
            $notification = $user->notifications()->latest()->get()->first(fn (DatabaseNotification $row): bool => ($row->data['alert_key'] ?? null) === $key);
            $state = $notification?->data['alert_state'] ?? null;
            if (is_string($state)) {
                return $state;
            }
        }

        return null;
    }
}
