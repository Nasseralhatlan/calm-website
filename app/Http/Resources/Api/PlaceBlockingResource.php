<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\PlaceBlocking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A host-visible calendar blocking. `source` distinguishes a manual block the
 * host made from one imported via calendar sync ('ical'); it defaults to
 * 'manual' until the calendar-sync migration adds the column. `reason` is the
 * host's own note (safe to show — this is their data).
 *
 * @mixin PlaceBlocking
 */
class PlaceBlockingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'source' => $this->source ?? 'manual',
            'reason' => $this->reason,
        ];
    }
}
