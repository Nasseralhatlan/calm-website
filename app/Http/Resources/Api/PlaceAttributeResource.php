<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\PlaceAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One picked attribute on a place (e.g. "2 bedrooms", "WiFi"). Includes the
 * attribute definition + its group so the mobile detail screen can render
 * facilities under section headings without a second fetch.
 *
 * @mixin PlaceAttribute
 */
class PlaceAttributeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attribute = $this->attribute;

        return [
            'id' => $this->id,
            'value' => $this->value,
            'description' => $this->description,
            'attribute' => $attribute ? [
                'id' => $attribute->id,
                'name_en' => $attribute->name_en,
                'name_ar' => $attribute->name_ar,
                'icon' => $attribute->icon,
                'type' => $attribute->type?->value,
                'is_highlighted' => (bool) $attribute->is_highlighted,
                'sort_order' => (int) $attribute->sort_order,
                'group' => $attribute->group ? [
                    'id' => $attribute->group->id,
                    'name_en' => $attribute->group->name_en,
                    'name_ar' => $attribute->group->name_ar,
                ] : null,
            ] : null,
        ];
    }
}
