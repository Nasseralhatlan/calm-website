<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Canonical place shape returned by EVERY list-of-places API endpoint.
 *
 * The mobile app reads this exact structure regardless of source endpoint
 * (most-liked, lists section, search, by city, by type). Keep additions
 * here in sync with the home screen card so all sections render uniformly.
 *
 * @mixin Place
 */
class PlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // `title`/`description` are the canonical value (= *_ar ?: *_en); the
            // *_ar/*_en pairs let the app show the user's language (like the
            // name_ar/name_en reference fields). Either may be null.
            'title' => $this->title,
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'price' => (int) $this->price,
            'per_day_prices' => [
                'sunday' => (int) $this->price_sunday,
                'monday' => (int) $this->price_monday,
                'tuesday' => (int) $this->price_tuesday,
                'wednesday' => (int) $this->price_wednesday,
                'thursday' => (int) $this->price_thursday,
                'friday' => (int) $this->price_friday,
                'saturday' => (int) $this->price_saturday,
            ],
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            // True = checkout is the morning AFTER the booking ends (overnight);
            // false = same day as the last booked day. Check-in is always day 1.
            'checkout_next_day' => (bool) $this->checkout_next_day,
            'max_guests' => $this->max_guests !== null ? (int) $this->max_guests : null,
            'rules' => $this->rules,
            'rules_ar' => $this->rules_ar,
            'rules_en' => $this->rules_en,
            // Cover = the first "shown outside" photo (falls back to the first
            // gallery photo if the host hasn't featured any).
            'cover_photo_url' => $this->coverPhoto?->url
                ?? ($this->whenLoaded('photos', fn () => $this->photos->first()?->url)),
            // Full gallery, ordered by the host-chosen sort_order. Each photo
            // carries its `attribute_id` (null = general) so the app can build
            // the grouped "view images" gallery, plus `sort_order` (order within
            // a group) and `featured_order` (its slot in the place-page showcase,
            // null = not shown outside).
            'photos' => $this->whenLoaded(
                'photos',
                fn () => $this->photos
                    ->map(fn ($p) => [
                        'id' => $p->id,
                        'url' => $p->url,
                        'attribute_id' => $p->place_attribute_id,
                        'sort_order' => (int) $p->sort_order,
                        'featured_order' => $p->featured_order,
                    ])
                    ->values(),
                [],
            ),
            // The curated "shown outside" set (place page), ≤10, ordered;
            // the first is the cover. Derived from the loaded photos.
            'featured_photos' => $this->whenLoaded(
                'photos',
                fn () => $this->photos
                    ->whereNotNull('featured_order')
                    ->sortBy('featured_order')
                    ->map(fn ($p) => [
                        'id' => $p->id,
                        'url' => $p->url,
                        'attribute_id' => $p->place_attribute_id,
                    ])
                    ->values(),
                [],
            ),
            'type' => $this->whenLoaded(
                'type',
                fn () => PlaceTypeResource::make($this->type)->resolve($request),
            ),
            'city' => $this->whenLoaded(
                'cityArea',
                fn () => $this->cityArea?->city
                    ? CityResource::make($this->cityArea->city)->resolve($request)
                    : null,
            ),
            'city_area' => $this->whenLoaded(
                'cityArea',
                fn () => $this->cityArea
                    ? [
                        'id' => $this->cityArea->id,
                        'name_en' => $this->cityArea->name_en,
                        'name_ar' => $this->cityArea->name_ar,
                    ]
                    : null,
            ),
            // Aggregates — present when eager-loaded via withCount/withAvg.
            // Cast defensively so the response is always typed even if the
            // caller forgot to load them (then they read as 0 / null).
            'likes_count' => (int) ($this->likes_count ?? 0),
            // Rating counts PUBLISHED reviews only (see PlaceService aggregates).
            'rating' => [
                'avg' => isset($this->published_reviews_avg_rate) ? round((float) $this->published_reviews_avg_rate, 2) : null,
                'count' => (int) ($this->published_reviews_count ?? 0),
            ],
            // True iff the authed user has liked this place. Requires the
            // `likedByMe` exists-load (see PlaceService::eagerHomeFields()).
            'is_liked' => (bool) ($this->liked_by_me ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
