<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendOperationalNotificationEmail;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Support\Collection;

final class OperationalNotificationSender
{
    /**
     * @param Collection<int, User> $recipients
     * @param array<string, mixed> $payload
     */
    public function send(Collection $recipients, array $payload): void
    {
        foreach ($recipients->unique('id') as $user) {
            if (! $user->is_active || $user->tenant_id !== ($payload['tenant_id'] ?? null)) {
                continue;
            }

            $notification = new OperationalNotification($payload);
            $user->notify($notification);

            if (! config('operations.notifications.email_enabled', false)) {
                continue;
            }

            $preference = NotificationPreference::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['tenant_id' => $user->tenant_id],
            );

            if (! $preference->allowsEmail((string) $payload['category'], (string) $payload['severity'])) {
                continue;
            }

            if ($preference->digest_frequency !== 'immediate' && ($payload['severity'] ?? null) !== 'critical') {
                continue;
            }

            $delivery = NotificationDelivery::query()->create([
                'tenant_id' => $user->tenant_id,
                'notification_id' => (string) $notification->id,
                'user_id' => $user->id,
                'channel' => 'email',
                'status' => NotificationDelivery::STATUS_PENDING,
            ]);

            SendOperationalNotificationEmail::dispatch($delivery->id, $payload);
        }
    }
}
