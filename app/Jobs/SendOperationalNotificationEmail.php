<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Tries(3)]
final class SendOperationalNotificationEmail implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $deliveryId,
        public readonly array $payload,
    ) {
        //
    }

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->findOrFail($this->deliveryId);
        $delivery->increment('attempts');
        $delivery->forceFill(['attempted_at' => now(), 'last_error' => null])->save();

        $user = User::query()->whereKey($delivery->user_id)->where('is_active', true)->firstOrFail();
        $user->notifyNow(new OperationalNotification($this->payload), ['mail']);
        $delivery->forceFill([
            'status' => NotificationDelivery::STATUS_SENT,
            'sent_at' => now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => NotificationDelivery::STATUS_FAILED,
            'last_error' => $exception?->getMessage(),
            'attempted_at' => now(),
        ]);
    }
}
