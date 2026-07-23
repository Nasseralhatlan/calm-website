<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Http\Requests\Concerns\DerivesCanonicalContent;
use App\Http\Requests\Concerns\NormalizesHostPhone;
use App\Http\Requests\Concerns\ValidatesAmenityPhotoRules;
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
    use DerivesCanonicalContent;
    use NormalizesHostPhone;
    use ValidatesAmenityPhotoRules;

    protected function prepareForValidation(): void
    {
        $this->normalizeHostPhone();
    }

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
            // Admin editing via the host flow (their own "my places" list —
            // where admin-entered places live) can transfer ownership by
            // phone, same as the admin edit route. Ignored for real hosts.
            'host_phone' => $this->user()?->isAdmin()
                ? ['nullable', 'string', 'regex:/^5\d{8}$/']
                : ['nullable'],
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
            // A map link the host pastes (Google Maps, etc.); required on submit,
            // only revealed to the guest once their booking is confirmed.
            'location_url' => ['required_without:latitude', 'nullable', 'string', 'url', 'max:2048'],
            // The map pin (decimal degrees). Optional — but always as a pair,
            // and it can replace the pasted URL (the server derives one).
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],

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
     * Place-column subset only — amenities and photo payloads are extracted
     * separately via the helpers below.
     *
     * @return array<string, mixed>
     */
    public function placeData(): array
    {
        $data = collect($this->validated())
            ->except(['host_phone', 'attributes', 'attribute_image_paths', 'extra_image_paths', 'featured'])
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
        // Mirror withValidator(): an edit that never touched the photo fields
        // is details-only — return the syncPhotos() no-op shape so the
        // existing gallery survives instead of being replaced with nothing.
        if (! $this->has('attribute_image_paths') && ! $this->has('extra_image_paths')) {
            return [];
        }

        $validated = $this->validated();

        return [
            'attribute_paths' => $validated['attribute_image_paths'] ?? [],
            'extra_paths' => $validated['extra_image_paths'] ?? [],
            'featured' => $validated['featured'] ?? [],
        ];
    }
}
