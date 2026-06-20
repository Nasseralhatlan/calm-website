<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One in-app notification for a single recipient. Bilingual; the app reads the
 * `title`/`body` resolved to the user's locale (see UserNotificationResource).
 */
class UserNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'title_ar',
        'title_en',
        'body_ar',
        'body_en',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Unread only. */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /** Localized title for the given locale (falls back to Arabic). */
    public function titleFor(string $locale): string
    {
        return $locale === 'en' ? $this->title_en : $this->title_ar;
    }

    /** Localized body for the given locale (falls back to Arabic). */
    public function bodyFor(string $locale): string
    {
        return $locale === 'en' ? $this->body_en : $this->body_ar;
    }
}
