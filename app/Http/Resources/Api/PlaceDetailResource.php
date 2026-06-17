<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail-screen shape: spreads the canonical PlaceResource at the top level
 * (so the mobile card component renders identically here as in any list) and
 * adds the extras a single-place screen needs — attributes, recent reviews,
 * host info. Returned by GET /api/places/{place}.
 *
 * @mixin Place
 */
class PlaceDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Spread the canonical place shape — every field PlaceResource
            // returns also lives at the top level here. Frontend's card +
            // detail screens can share the same parser.
            ...PlaceResource::make($this->resource)->resolve($request),

            // ── Detail-only extras ─────────────────────────────────────────
            // The grouped "view images" gallery, ready to render: photos grouped
            // by amenity, ordered WITHIN a group by sort_order, and the GROUPS
            // ordered by each group's earliest sort_order (so the host's section
            // arrangement is honoured — a section pushed down leads later). The
            // general (no-amenity) group carries a null `attribute`. This is the
            // canonical section order; deriving it client-side from `photos[]`
            // gives the same result (group by attribute_id, order by min sort_order).
            'photo_groups' => $this->whenLoaded('photos', fn () => $this->buildPhotoGroups()),

            'attributes' => PlaceAttributeResource::collection($this->whenLoaded('attributeValues')),
            'reviews_recent' => PlaceReviewResource::collection($this->whenLoaded('reviews')),
            'host' => $this->whenLoaded('host', fn () => [
                'id' => $this->host?->id,
                // Lightweight public profile only — phone/email/etc. stay
                // private. When bookings land we'll add a verification badge.
                'name' => $this->host?->name,
                'joined_at' => $this->host?->created_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Group the loaded photos into render-ready gallery sections.
     *
     * @return list<array<string, mixed>>
     */
    private function buildPhotoGroups(): array
    {
        // attribute_id → Attribute meta, sourced from the loaded amenity rows
        // when present (so we can attach name/icon to each section).
        $attrMap = $this->relationLoaded('attributeValues')
            ? $this->attributeValues
                ->filter(fn ($pa) => $pa->attribute !== null)
                ->keyBy('attribute_id')
                ->map(fn ($pa) => $pa->attribute)
            : collect();

        return $this->photos
            ->groupBy(fn ($p) => $p->place_attribute_id ?? '__general__')
            ->map(function ($photos, $key) use ($attrMap) {
                $sorted = $photos->sortBy('sort_order')->values();
                $attribute = $key === '__general__' ? null : $attrMap->get($key);

                return [
                    'attribute_id' => $key === '__general__' ? null : $key,
                    'attribute' => $attribute ? [
                        'id' => $attribute->id,
                        'name_en' => $attribute->name_en,
                        'name_ar' => $attribute->name_ar,
                        'icon' => $attribute->icon,
                    ] : null,
                    // The group's leading sort_order — its slot in the gallery.
                    'min_sort_order' => (int) $sorted->first()->sort_order,
                    'photos' => $sorted->map(fn ($p) => [
                        'id' => $p->id,
                        'url' => $p->url,
                        'sort_order' => (int) $p->sort_order,
                        'featured_order' => $p->featured_order,
                    ])->values(),
                ];
            })
            ->sortBy('min_sort_order')
            ->values()
            ->all();
    }
}
