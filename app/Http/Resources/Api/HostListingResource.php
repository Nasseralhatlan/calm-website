<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A host's own listing, for the host app's "My listings". Unlike the public
 * PlaceResource it exposes the host-only lifecycle fields (status, review_status,
 * rejection_reason) and the counts a host dashboard shows. Returned regardless of
 * visibility (drafts/pending/rejected included).
 *
 * @mixin Place
 */
class HostListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'cover_photo_url' => $this->coverPhoto?->url,
            'price' => (int) $this->price,
            'max_guests' => $this->max_guests !== null ? (int) $this->max_guests : null,
            'type' => $this->whenLoaded('type', fn () => $this->type ? [
                'id' => $this->type->id,
                'name_en' => $this->type->name_en,
                'name_ar' => $this->type->name_ar,
                'icon' => $this->type->icon,
            ] : null),
            'city' => $this->whenLoaded('cityArea', fn () => $this->cityArea?->city ? [
                'id' => $this->cityArea->city->id,
                'name_en' => $this->cityArea->city->name_en,
                'name_ar' => $this->cityArea->city->name_ar,
            ] : null),
            'city_area' => $this->whenLoaded('cityArea', fn () => $this->cityArea ? [
                'id' => $this->cityArea->id,
                'name_en' => $this->cityArea->name_en,
                'name_ar' => $this->cityArea->name_ar,
            ] : null),
            // Host-only lifecycle.
            'status' => $this->status->value,
            'review_status' => $this->review_status->value,
            'rejection_reason' => $this->rejection_reason,
            // Dashboard counts (present when loaded via withCount/withAvg).
            'likes_count' => (int) ($this->likes_count ?? 0),
            'bookings_count' => (int) ($this->bookings_count ?? 0),
            'rating' => [
                'avg' => isset($this->published_reviews_avg_rate) ? round((float) $this->published_reviews_avg_rate, 2) : null,
                'count' => (int) ($this->published_reviews_count ?? 0),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
