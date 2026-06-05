<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;

class StorePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'draft_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'place_type_id' => ['required', 'uuid', 'exists:place_types,id'],
            'city_area_id' => ['required', 'uuid', 'exists:city_areas,id'],
            'price' => ['required', 'integer', 'min:0'],
            'check_in_time' => ['required', 'string', 'max:8'],
            'check_out_time' => ['required', 'string', 'max:8'],
            'rules' => ['nullable', 'string', 'max:10000'],

            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'uuid', 'exists:attributes,id'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
            'attributes.*.description' => ['nullable', 'string', 'max:1000'],

            'attribute_image_paths' => ['nullable', 'array'],
            'attribute_image_paths.*' => ['array'],
            'attribute_image_paths.*.*' => ['string', 'max:500'],
            'extra_image_paths' => ['nullable', 'array'],
            'extra_image_paths.*' => ['string', 'max:500'],
            'cover_image' => ['nullable', 'string', 'max:255'],
        ];

        foreach (Place::PRICE_COLUMNS as $column) {
            $rules[$column] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * Place-column subset only — control fields and child-table payloads
     * are extracted separately via the helpers below.
     *
     * @return array<string, mixed>
     */
    public function placeData(): array
    {
        return collect($this->validated())
            ->except(['draft_id', 'attributes', 'attribute_image_paths', 'extra_image_paths', 'cover_image'])
            ->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attributesData(): array
    {
        $raw = $this->validated()['attributes'] ?? [];

        return array_values(array_map(fn (array $a): array => [
            'attribute_id' => (string) $a['attribute_id'],
            'value' => isset($a['value']) ? (string) $a['value'] : null,
            'description' => $a['description'] ?? null,
        ], $raw));
    }

    /**
     * @return array<string, mixed>
     */
    public function photosData(): array
    {
        $validated = $this->validated();

        return [
            'attribute_paths' => $validated['attribute_image_paths'] ?? [],
            'extra_paths'     => $validated['extra_image_paths']     ?? [],
            'cover_key'       => $validated['cover_image']           ?? null,
        ];
    }
}
