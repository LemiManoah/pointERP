<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Operations\Equipment\ProcessMaintenanceDueSchedules;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('equipment:process-maintenance-due {--date=} {--tenant=}')]
#[Description('Evaluate equipment maintenance schedules and notify responsible fleet users.')]
final class ProcessEquipmentMaintenanceDueCommand extends Command
{
    public function handle(ProcessMaintenanceDueSchedules $action): int
    {
        $asOf = $this->option('date') ? CarbonImmutable::parse((string) $this->option('date'))->endOfDay() : CarbonImmutable::now();
        $tenant = $this->option('tenant');
        $result = $action->handle($asOf, is_string($tenant) && $tenant !== '' ? $tenant : null);
        $this->info(sprintf('Maintenance schedules: %d due soon, %d overdue, %d notification(s).', $result['due_soon'], $result['overdue'], $result['notifications']));

        return self::SUCCESS;
    }
}
