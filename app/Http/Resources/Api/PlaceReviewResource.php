<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\PlaceReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One guest review. The reviewer is surfaced by first name only (derived from
 * the booking's guest); `status` is meaningful in the guest's own / host /
 * admin contexts (public lists only ever contain published reviews). `place`
 * is included when loaded (host's "reviews on my places" list).
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
            'status' => $this->status?->value,
            'reviewer_name' => Str::of((string) $this->guest?->name)->trim()->explode(' ')->first() ?: null,
            'reviewer_avatar_url' => $this->guest?->avatar_url,
            'place' => $this->whenLoaded('place', fn (): array => [
                'id' => $this->place?->id,
                'title' => $this->place?->title,
                'cover_photo_url' => $this->place?->coverPhoto?->url,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
