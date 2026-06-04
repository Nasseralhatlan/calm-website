<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;

class SaveDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Drafts only require a place_type_id — everything else is nullable
     * because the host is mid-wizard.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'draft_id' => ['nullable', 'integer'],
            'place_type_id' => ['required', 'integer', 'exists:place_types,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'city_area_id' => ['nullable', 'integer', 'exists:city_areas,id'],
            'price' => ['nullable', 'integer', 'min:0'],
            'check_in_time' => ['nullable', 'string', 'max:8'],
            'check_out_time' => ['nullable', 'string', 'max:8'],
            'rules' => ['nullable', 'string', 'max:10000'],

            // Attributes — host's selected facilities. Sent as an array of objects
            // keyed by attribute_id in either array form ([0 => {attribute_id,...}])
            // or the form-encoded shape ([<id> => {attribute_id,...}]).
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
            'attributes.*.description' => ['nullable', 'string', 'max:1000'],

            // Photos — uploaded paths already on S3 (via presigned PUT).
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
     * Only the place-column subset for the service — keep control fields
     * (draft_id, attributes, photo paths, cover) out so they don't get
     * shoved into the place row.
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
     * Normalized list of {attribute_id, value, description} ready for the service.
     *
     * @return list<array<string, mixed>>
     */
    public function attributesData(): array
    {
        $raw = $this->validated()['attributes'] ?? [];

        return array_values(array_map(fn (array $a): array => [
            'attribute_id' => (int) $a['attribute_id'],
            'value' => isset($a['value']) ? (string) $a['value'] : null,
            'description' => $a['description'] ?? null,
        ], $raw));
    }

    /**
     * Photo payload bundled for {@see PlaceService::syncPhotos()}.
     *
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
