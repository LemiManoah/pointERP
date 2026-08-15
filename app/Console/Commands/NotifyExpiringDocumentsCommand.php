<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Operations\Documents\NotifyExpiringDocuments;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('documents:notify-expiring {--date=} {--tenant=}')]
#[Description('Notify document owners about documents expiring within 30 days.')]
final class NotifyExpiringDocumentsCommand extends Command
{
    public function handle(NotifyExpiringDocuments $action): int
    {
        $asOf = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $tenant = $this->option('tenant');
        $sent = $action->handle($asOf, is_string($tenant) && $tenant !== '' ? $tenant : null);

        $this->info(sprintf('Created %d document expiry notification(s).', $sent));

        return self::SUCCESS;
    }
}

