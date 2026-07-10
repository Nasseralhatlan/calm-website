<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One amenity definition from the catalog — everything the place wizard needs
 * to render its input: bilingual name + question, input type, whether a photo
 * accompanies the value, and the options list for select types.
 *
 * @mixin Attribute
 */
class AttributeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'icon' => $this->icon,
            'question_ar' => $this->question_ar,
            'question_en' => $this->question_en,
            'type' => $this->type?->value,
            'photo_rule' => $this->photo_rule?->value,
            'is_highlighted' => (bool) $this->is_highlighted,
            'options' => $this->options,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
