<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Operations\DailySiteReports\GenerateExpectedDailySiteReports;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dsr:generate-expected {--date=} {--from=} {--to=} {--site=} {--tenant=}')]
#[Description('Generate expected DSR obligations using tenant, project and site reporting calendars.')]
final class GenerateExpectedDailySiteReportsCommand extends Command
{
    public function handle(GenerateExpectedDailySiteReports $action): int
    {
        $date = $this->option('date');
        $from = CarbonImmutable::parse((string) ($this->option('from') ?: $date ?: now()->toDateString()));
        $to = CarbonImmutable::parse((string) ($this->option('to') ?: $date ?: $from->toDateString()));

        if ($to->lessThan($from)) {
            $this->error('The end date must be on or after the start date.');

            return self::FAILURE;
        }

        $created = $action->handle(
            from: $from,
            to: $to,
            tenantId: $this->optionString('tenant'),
            siteId: $this->optionString('site'),
        );

        $this->info(sprintf('Generated %d expected DSR obligation(s).', $created));

        return self::SUCCESS;
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
