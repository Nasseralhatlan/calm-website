<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\PlaceReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One review on a place's detail screen. Anonymized — no reviewer identity
 * exposed (reviews don't link to a user_id today; when bookings ship the
 * reviewer will be derived from the booking and surfaced as a display name).
 *
 * @mixin PlaceReview
 */
class PlaceReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rate' => (int) $this->rate,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
