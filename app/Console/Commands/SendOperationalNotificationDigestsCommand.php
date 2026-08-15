<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendOperationalNotificationDigestEmail;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

#[Signature('notifications:send-digests')]
#[Description('Queue daily or weekly operational notification digests')]
final class SendOperationalNotificationDigestsCommand extends Command
{
    public function handle(): int
    {
        if (! config('operations.notifications.email_enabled', false)) {
            $this->components->info('Operational email notifications are disabled.');

            return self::SUCCESS;
        }

        $queued = 0;
        NotificationPreference::query()
            ->where('email_enabled', true)
            ->whereIn('digest_frequency', ['daily', 'weekly'])
            ->with('user')
            ->each(function (NotificationPreference $preference) use (&$queued): void {
                if ($preference->digest_frequency === 'weekly' && ! now()->isMonday()) {
                    return;
                }

                $user = $preference->user;

                if (! $user instanceof User || ! $user->is_active) {
                    return;
                }

                $since = $preference->digest_frequency === 'weekly' ? now()->subWeek() : now()->subDay();
                $deliveredIds = NotificationDelivery::query()->where('user_id', $user->id)->pluck('notification_id');
                $notifications = $user->unreadNotifications()
                    ->where('data->tenant_id', $user->tenant_id)
                    ->where('created_at', '>=', $since)
                    ->whereNotIn('id', $deliveredIds)
                    ->latest()
                    ->get()
                    ->filter(fn (DatabaseNotification $notification): bool => $this->allows($preference, $notification))
                    ->values();

                if ($notifications->isEmpty()) {
                    return;
                }

                $deliveryIds = $notifications->map(function (DatabaseNotification $notification) use ($user): string {
                    return NotificationDelivery::query()->create([
                        'tenant_id' => $user->tenant_id,
                        'notification_id' => $notification->id,
                        'user_id' => $user->id,
                        'channel' => 'email_digest',
                        'status' => NotificationDelivery::STATUS_PENDING,
                    ])->id;
                })->all();

                SendOperationalNotificationDigestEmail::dispatch($user->id, $deliveryIds, [
                    'tenant_id' => $user->tenant_id,
                    'category' => 'operational_digest',
                    'severity' => 'info',
                    'title' => ucfirst($preference->digest_frequency).' PointERP operations digest',
                    'message' => $this->summary($notifications),
                    'action_url' => '/notifications',
                ]);
                $queued++;
            });

        $this->components->info(sprintf('Queued %d notification digest(s).', $queued));

        return self::SUCCESS;
    }

    private function allows(NotificationPreference $preference, DatabaseNotification $notification): bool
    {
        $category = $notification->data['category'] ?? 'general';
        $severity = $notification->data['severity'] ?? 'info';

        return $severity === 'critical'
            || (is_string($category) && ! in_array($category, $preference->muted_email_categories ?? [], true));
    }

    /** @param Collection<int, DatabaseNotification> $notifications */
    private function summary(Collection $notifications): string
    {
        $titles = $notifications->take(5)
            ->map(fn (DatabaseNotification $notification): string => (string) ($notification->data['title'] ?? 'Operational update'));
        $remaining = $notifications->count() - $titles->count();

        return sprintf(
            '%d unread update(s): %s%s',
            $notifications->count(),
            $titles->implode('; '),
            $remaining > 0 ? sprintf('; and %d more.', $remaining) : '.',
        );
    }
}
