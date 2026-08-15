<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $notification_id
 * @property-read string $user_id
 * @property-read string $channel
 * @property-read string $status
 * @property-read int $attempts
 * @property-read User|null $user
 */
#[Fillable(['tenant_id', 'notification_id', 'user_id', 'channel', 'status', 'attempts', 'last_error', 'attempted_at', 'sent_at'])]
final class NotificationDelivery extends Model
{
    use HasUuids;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_SENT = 'sent';
    public const string STATUS_FAILED = 'failed';

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'attempts' => 'integer',
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DatabaseNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(DatabaseNotification::class, 'notification_id');
    }
}
