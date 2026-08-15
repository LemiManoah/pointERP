<?php

declare(strict_types=1);

namespace App\Actions\Operations\Documents;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OperationalNotificationSender;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;

final readonly class NotifyExpiringDocuments
{
    public function __construct(
        private TenantContext $tenantContext,
        private OperationalNotificationSender $notifications,
    ) {
        //
    }

    public function handle(CarbonImmutable $asOf, ?string $tenantId = null): int
    {
        $sent = 0;
        $tenants = Tenant::query()
            ->active()
            ->when($tenantId, fn ($query, string $id) => $query->whereKey($id))
            ->get();

        foreach ($tenants as $tenant) {
            $this->tenantContext->set($tenant);
            $documents = Document::query()
                ->with('owner')
                ->active()
                ->whereNotNull('expires_on')
                ->whereBetween('expires_on', [$asOf->toDateString(), $asOf->addDays(30)->toDateString()])
                ->get();

            foreach ($documents as $document) {
                $recipient = $document->owner;
                if (! $recipient instanceof User) {
                    continue;
                }

                if (! $recipient->is_active) {
                    continue;
                }

                $alreadySent = $recipient->notifications()
                    ->where('data->tenant_id', $tenant->id)
                    ->where('data->category', 'document_expiry')
                    ->where('data->document_id', $document->id)
                    ->whereDate('created_at', $asOf->toDateString())
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $this->notifications->send(collect([$recipient]), [
                    'tenant_id' => $document->tenant_id,
                    'branch_id' => $document->branch_id,
                    'document_id' => $document->id,
                    'category' => 'document_expiry',
                    'severity' => 'warning',
                    'title' => 'Document expires soon',
                    'message' => sprintf('%s expires on %s.', $document->title, $document->expires_on->toFormattedDateString()),
                    'action_url' => '/documents/'.$document->id,
                ]);
                $sent++;
            }
        }

        return $sent;
    }
}
