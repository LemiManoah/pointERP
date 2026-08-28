<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Operations\Inventory\ProcessInventoryAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:process-alerts {--date=} {--tenant=}')]
#[Description('Check inventory exceptions and send deduplicated operational notifications.')]
final class ProcessInventoryAlertsCommand extends Command
{
    public function handle(ProcessInventoryAlerts $action): int
    {
        $asOf = $this->option('date') ? CarbonImmutable::parse((string) $this->option('date'))->endOfDay() : CarbonImmutable::now();
        $tenant = $this->option('tenant');
        $result = $action->handle($asOf, is_string($tenant) && $tenant !== '' ? $tenant : null);
        $this->info(sprintf('Inventory alerts: %d low stock, %d recovered, %d overdue POs, %d DSR exceptions, %d notification(s).', $result['low_stock'], $result['recovered'], $result['overdue_orders'], $result['unreconciled_dsr'], $result['notifications']));

        return self::SUCCESS;
    }
}
