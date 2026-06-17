<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\PlaceList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A curated home-screen section: list metadata + its member places rendered
 * through the canonical PlaceResource so the section row uses the same card
 * shape as any other place list on the home screen.
 *
 * @mixin PlaceList
 */
class PlaceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'icon' => $this->icon,
            'sort_order' => (int) $this->sort_order,
            'places' => PlaceResource::collection($this->whenLoaded('places')),
        ];
    }
}
