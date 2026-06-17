<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unavailable-dates shape for the mobile calendar. Wraps the plain array the
 * PlaceAvailabilityService computes (not an Eloquent model).
 *
 *   unavailable_dates  → flat Y-m-d list; mark each calendar cell.
 *   unavailable_ranges → the same days folded into contiguous blocks; shade a
 *                        run cheaply instead of testing every cell.
 *
 * Returned by GET /api/places/{place}/unavailable-dates.
 */
class PlaceAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'place_id' => $this->resource['place_id'],
            // Echo back the resolved window so the client knows exactly what
            // span these dates cover (defaults/clamping applied server-side).
            'from' => $this->resource['from'],
            'to' => $this->resource['to'],
            'unavailable_dates' => $this->resource['dates'],
            'unavailable_ranges' => $this->resource['ranges'],
        ];
    }
}
