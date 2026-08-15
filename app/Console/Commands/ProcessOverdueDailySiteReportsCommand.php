<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Operations\DailySiteReports\ProcessOverdueDailySiteReports;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dsr:process-overdue {--date=} {--site=} {--tenant=}')]
#[Description('Mark overdue DSR obligations missing, notify owners and escalate repeated misses.')]
final class ProcessOverdueDailySiteReportsCommand extends Command
{
    public function handle(ProcessOverdueDailySiteReports $action): int
    {
        $asOf = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))->endOfDay()
            : CarbonImmutable::now();

        $result = $action->handle(
            asOf: $asOf,
            tenantId: $this->optionString('tenant'),
            siteId: $this->optionString('site'),
        );

        $this->info(sprintf(
            'Processed overdue DSRs: %d marked missing, %d reminder(s), %d escalation(s).',
            $result['missing'],
            $result['notified'],
            $result['escalated'],
        ));

        return self::SUCCESS;
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

