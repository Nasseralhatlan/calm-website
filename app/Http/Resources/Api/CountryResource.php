<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
class CountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_code' => $this->country_code,
            'dial_code' => $this->dial_code,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'avatar' => $this->avatar,
        ];
    }
}
