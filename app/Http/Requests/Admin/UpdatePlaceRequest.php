<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Http\Requests\Concerns\DerivesCanonicalContent;
use App\Http\Requests\Concerns\ValidatesAmenityPhotoRules;
use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlaceRequest extends FormRequest
{
    use DerivesCanonicalContent;
    use ValidatesAmenityPhotoRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Form posts `lists[]` only when at least one checkbox is checked. Default
     * to [] so an empty selection clears membership instead of being ignored.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('lists')) {
            $this->merge(['lists' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'title_ar' => ['nullable', 'required_without:title_en', 'string', 'max:255'],
            'title_en' => ['nullable', 'required_without:title_ar', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:10000'],
            'description_en' => ['nullable', 'string', 'max:10000'],
            'place_type_id' => ['required', 'uuid', 'exists:place_types,id'],
            'city_area_id' => ['required', 'uuid', 'exists:city_areas,id'],
            'price' => ['required', 'integer', 'min:0'],
            'check_in_time' => ['required', 'string', 'max:8'],
            'check_out_time' => ['required', 'string', 'max:8'],
            'checkout_next_day' => ['sometimes', 'boolean'],
            'max_guests' => ['required', 'integer', 'between:1,50'],
            'rules_ar' => ['nullable', 'string', 'max:10000'],
            'rules_en' => ['nullable', 'string', 'max:10000'],
            // A map link the host pastes (Google Maps, etc.); only revealed to
            // the guest once their booking is confirmed.
            'location_url' => ['required_without:latitude', 'nullable', 'string', 'url', 'max:2048'],
            // The map pin (decimal degrees). Optional — but always as a pair,
            // and it can replace the pasted URL (the server derives one).
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::enum(PlaceStatus::class)],
            'review_status' => ['required', Rule::enum(PlaceReviewStatus::class)],
            // Admin can edit / clear the rejection feedback directly.
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
            // Curated-list membership — multi-select from edit form.
            'lists' => ['array'],
            'lists.*' => ['uuid', 'exists:place_lists,id'],

            // Amenities — same shape the wizard submits.
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'uuid', 'exists:attributes,id'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
            'attributes.*.description' => ['nullable', 'string', 'max:1000'],

            // Identical units ("وحدة ١"…): optional, full desired state.
            'units' => ['nullable', 'array'],
            'units.*.id' => ['nullable', 'uuid'],
            'units.*.name' => ['required', 'string', 'max:100'],

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

        // Add a min:0 integer rule for each day-specific price column.
        foreach (Place::PRICE_COLUMNS as $column) {
            $rules[$column] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        // Only enforce photo rules when the edit actually re-submits photos —
        // a details-only edit leaves the existing gallery untouched.
        if (! $this->has('attribute_image_paths') && ! $this->has('extra_image_paths')) {
            return;
        }

        $validator->after(fn (Validator $validator) => $this->enforceAmenityPhotoRules($validator));
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
     * Place-column subset (incl. status / review_status / rejection_reason —
     * admins control these directly). Amenities, photos and list membership
     * are extracted separately via the helpers below.
     *
     * @return array<string, mixed>
     */
    public function placeData(): array
    {
        $data = collect($this->validated())
            ->except(['attributes', 'attribute_image_paths', 'extra_image_paths', 'featured', 'lists'])
            ->toArray();

        return $this->withCanonicalContent($data);
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

    /**
     * @return list<string>
     */
    public function listsData(): array
    {
        return array_values($this->validated()['lists'] ?? []);
    }
}
