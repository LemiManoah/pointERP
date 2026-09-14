<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Workforce\ProcessWorkforceExceptions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workforce:process-exceptions {--date=} {--tenant=}')]
#[Description('Check overdue attendance and send deduplicated workforce notifications.')]
final class ProcessWorkforceExceptionsCommand extends Command
{
    public function handle(ProcessWorkforceExceptions $action): int
    {
        $asOf = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))->endOfDay()
            : CarbonImmutable::now();
        $tenant = $this->option('tenant');
        $result = $action->handle($asOf, is_string($tenant) && $tenant !== '' ? $tenant : null);

        $this->info(sprintf(
            'Workforce exceptions: %d overdue attendance register(s), %d notification(s).',
            $result['overdue_registers'],
            $result['notifications'],
        ));

        return self::SUCCESS;
    }
}
