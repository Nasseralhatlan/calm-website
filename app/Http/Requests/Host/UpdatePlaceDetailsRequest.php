<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Host self-service edit of an existing place's details. Ownership is enforced
 * in the controller; this validates the editable columns only — review_status
 * and status are NOT host-settable (the service forces PendingReview on save).
 */
class UpdatePlaceDetailsRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'place_type_id' => ['required', 'uuid', 'exists:place_types,id'],
            'city_area_id' => ['required', 'uuid', 'exists:city_areas,id'],
            'price' => ['required', 'integer', 'min:0'],
            'check_in_time' => ['required', 'string', 'max:8'],
            'check_out_time' => ['required', 'string', 'max:8'],
            'checkout_next_day' => ['sometimes', 'boolean'],
            'max_guests' => ['required', 'integer', 'between:1,50'],
            'rules' => ['nullable', 'string', 'max:10000'],

            // Amenities — same shape the wizard submits.
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'uuid', 'exists:attributes,id'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
            'attributes.*.description' => ['nullable', 'string', 'max:1000'],

            // Photos — paths already uploaded to S3 via the presign endpoint.
            'attribute_image_paths' => ['nullable', 'array'],
            // Each section (per amenity) holds at most 10 images.
            'attribute_image_paths.*' => ['array', 'max:10'],
            'attribute_image_paths.*.*' => ['string', 'max:500'],
            // The "other" section also caps at 10.
            'extra_image_paths' => ['nullable', 'array', 'max:10'],
            'extra_image_paths.*' => ['string', 'max:500'],
            'featured' => ['nullable', 'array', 'max:10'],
            'featured.*' => ['string', 'max:255'],
        ];

        foreach (Place::PRICE_COLUMNS as $column) {
            $rules[$column] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        // Only enforce the 5-image minimum when the edit actually re-submits
        // photos — a details-only edit leaves the existing gallery untouched.
        if (! $this->has('attribute_image_paths') && ! $this->has('extra_image_paths')) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $total = collect($this->input('attribute_image_paths', []))->flatten()->filter()->count()
                + collect($this->input('extra_image_paths', []))->filter()->count();

            if ($total < 5) {
                $validator->errors()->add('images', __('A place must have at least :min images.', ['min' => 5]));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attribute_image_paths.*.max' => __('Each section can have at most :max images.', ['max' => 10]),
            'extra_image_paths.max' => __('Each section can have at most :max images.', ['max' => 10]),
        ];
    }

    /**
     * Place-column subset only — amenities and photo payloads are extracted
     * separately via the helpers below.
     *
     * @return array<string, mixed>
     */
    public function placeData(): array
    {
        return collect($this->validated())
            ->except(['attributes', 'attribute_image_paths', 'extra_image_paths', 'featured'])
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
            'extra_paths' => $validated['extra_image_paths'] ?? [],
            'featured' => $validated['featured'] ?? [],
        ];
    }
}
