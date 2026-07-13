<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Place;
use App\Models\PlaceAttribute;
use App\Models\PlacePhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A host's own place in full EDITABLE form — everything the mobile wizard
 * needs to resume a draft or edit a listing, mirroring the web edit() payload.
 * Unlike the public PlaceDetailResource it exposes raw columns (both language
 * fields, per-day prices, lifecycle) and a FLAT photo list: the app regroups
 * photos into attribute_image_paths / extra_image_paths / featured exactly
 * like the web wizard JS does, which keeps the server shape web-consistent.
 *
 * @mixin Place
 */
class HostPlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Host-only lifecycle.
            'status' => $this->status->value,
            'review_status' => $this->review_status->value,
            'rejection_reason' => $this->rejection_reason,
            'last_step' => $this->last_step !== null ? (int) $this->last_step : null,
            // Raw bilingual content — the wizard edits both languages directly.
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'rules_ar' => $this->rules_ar,
            'rules_en' => $this->rules_en,
            'place_type_id' => $this->place_type_id,
            // city_id lets the app pre-select the city step of the 2-stage
            // city → area picker without a second lookup.
            'city_id' => $this->cityArea?->city_id,
            'city_area_id' => $this->city_area_id,
            'price' => $this->price,
            'price_sunday' => $this->price_sunday,
            'price_monday' => $this->price_monday,
            'price_tuesday' => $this->price_tuesday,
            'price_wednesday' => $this->price_wednesday,
            'price_thursday' => $this->price_thursday,
            'price_friday' => $this->price_friday,
            'price_saturday' => $this->price_saturday,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'checkout_next_day' => $this->checkout_next_day,
            'max_guests' => $this->max_guests,
            'location_url' => $this->location_url,
            // Owner sees the EXACT pin (edit/resume hydration).
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'attributes' => $this->attributeValues->map(fn (PlaceAttribute $value): array => [
                'attribute_id' => $value->attribute_id,
                'value' => $value->value,
                'description' => $value->description,
            ])->values()->all(),
            'photos' => $this->photos->map(fn (PlacePhoto $photo): array => [
                'place_attribute_id' => $photo->place_attribute_id,
                'path' => $photo->path,
                'url' => $photo->url,
                'featured_order' => $photo->featured_order,
                'sort_order' => (int) $photo->sort_order,
            ])->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
