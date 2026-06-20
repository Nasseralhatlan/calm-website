<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record of an admin "send updates to users" broadcast. The per-recipient
 * copies live in `user_notifications`.
 */
class NotificationBroadcast extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_user_id',
        'audience',
        'title_ar',
        'title_en',
        'body_ar',
        'body_en',
        'data',
        'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'recipients_count' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
