<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\AttributeGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An amenity group with its attributes nested in admin-controlled order —
 * the mobile wizard renders one section per group, exactly like the web.
 *
 * @mixin AttributeGroup
 */
class AttributeGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'sort_order' => (int) $this->sort_order,
            // Standalone sections render as their own block in the app.
            'is_standalone' => (bool) $this->is_standalone,
            'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
        ];
    }
}
