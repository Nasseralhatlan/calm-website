<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 */
class CityResource extends JsonResource
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
            'avatar' => $this->avatar,
            'country_id' => $this->country_id,
            // Areas within the city — present when eager-loaded (the cities API
            // loads them so the app can render the city → area picker in one call).
            'areas' => $this->whenLoaded('areas', fn () => $this->areas
                ->map(fn ($area) => [
                    'id' => $area->id,
                    'name_en' => $area->name_en,
                    'name_ar' => $area->name_ar,
                ])
                ->values()),
        ];
    }
}
