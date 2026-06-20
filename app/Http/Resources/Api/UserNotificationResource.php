<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One in-app notification, already resolved to the authed user's language.
 *
 * @mixin UserNotification
 */
class UserNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $request->user()?->locale === 'en' ? 'en' : 'ar';

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->titleFor($locale),
            'body' => $this->bodyFor($locale),
            'data' => $this->data ?? [],
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
