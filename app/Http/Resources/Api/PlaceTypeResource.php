<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\PlaceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlaceType
 */
class PlaceTypeResource extends JsonResource
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
            'icon' => $this->icon,
        ];
    }
}
