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
final class SendOperationalNotificationDigestEmail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $deliveryIds
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $userId,
        public readonly array $deliveryIds,
        public readonly array $payload,
    ) {
        //
    }

    public function handle(): void
    {
        $deliveries = NotificationDelivery::query()->whereIn('id', $this->deliveryIds)->get();
        $deliveries->each(function (NotificationDelivery $delivery): void {
            $delivery->increment('attempts');
            $delivery->forceFill(['attempted_at' => now(), 'last_error' => null])->save();
        });

        $user = User::query()->whereKey($this->userId)->where('is_active', true)->firstOrFail();
        $user->notifyNow(new OperationalNotification($this->payload), ['mail']);

        NotificationDelivery::query()->whereIn('id', $this->deliveryIds)->update([
            'status' => NotificationDelivery::STATUS_SENT,
            'sent_at' => now(),
            'last_error' => null,
            'updated_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        NotificationDelivery::query()->whereIn('id', $this->deliveryIds)->update([
            'status' => NotificationDelivery::STATUS_FAILED,
            'attempted_at' => now(),
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            'updated_at' => now(),
        ]);
    }
}
