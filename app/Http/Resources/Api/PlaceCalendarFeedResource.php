<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\PlaceCalendarFeed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An imported external calendar (Airbnb / Gathern / Google URL) on one of the
 * host's places, with its last sync outcome for the sync screen's status row.
 *
 * @mixin PlaceCalendarFeed
 */
class PlaceCalendarFeedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'name' => $this->name,
            'url' => $this->url,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_status' => $this->last_status,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
