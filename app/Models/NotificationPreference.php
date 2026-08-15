<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $user_id
 * @property-read bool $email_enabled
 * @property-read list<string>|null $muted_email_categories
 * @property-read string $digest_frequency
 * @property-read User|null $user
 */
#[Fillable(['tenant_id', 'user_id', 'email_enabled', 'muted_email_categories', 'digest_frequency'])]
final class NotificationPreference extends Model
{
    /** @use HasFactory<Factory<NotificationPreference>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'muted_email_categories' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowsEmail(string $category, string $severity): bool
    {
        if ($severity === 'critical') {
            return true;
        }

        return $this->email_enabled
            && ! in_array($category, $this->muted_email_categories ?? [], true);
    }
}
