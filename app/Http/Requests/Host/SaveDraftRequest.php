<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Http\Requests\Concerns\DerivesCanonicalContent;
use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;

class SaveDraftRequest extends FormRequest
{
    use DerivesCanonicalContent;

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
            'draft_id' => ['nullable', 'uuid'],
            // Admin-only: nullable on drafts so partial saves work before the
            // admin types the host phone. The format rule still applies if
            // anything is supplied. See StorePlaceRequest for the strict
            // final-submit version.
            'host_phone' => $this->user()?->isAdmin()
                ? ['nullable', 'string', 'regex:/^5\d{8}$/']
                : ['nullable'],
            'place_type_id' => ['required', 'uuid', 'exists:place_types,id'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:10000'],
            'description_en' => ['nullable', 'string', 'max:10000'],
            'city_area_id' => ['nullable', 'uuid', 'exists:city_areas,id'],
            'price' => ['nullable', 'integer', 'min:0'],
            'check_in_time' => ['nullable', 'string', 'max:8'],
            'check_out_time' => ['nullable', 'string', 'max:8'],
            'checkout_next_day' => ['sometimes', 'boolean'],
            'max_guests' => ['nullable', 'integer', 'between:1,50'],
            'rules_ar' => ['nullable', 'string', 'max:10000'],
            'rules_en' => ['nullable', 'string', 'max:10000'],
            // Map link (Google Maps, etc.) — optional while drafting. Not
            // url-validated here so a partial paste doesn't block auto-save.
            'location_url' => ['nullable', 'string', 'max:2048'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'last_step' => ['nullable', 'integer', 'min:1', 'max:20'],

            // Attributes — host's selected facilities. Sent as an array of objects
            // keyed by attribute_id in either array form ([0 => {attribute_id,...}])
            // or the form-encoded shape ([<id> => {attribute_id,...}]).
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'uuid', 'exists:attributes,id'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
            'attributes.*.description' => ['nullable', 'string', 'max:1000'],

            // Identical units ("وحدة ١"…): optional, full desired state.
            'units' => ['nullable', 'array'],
            'units.*.id' => ['nullable', 'uuid'],
            'units.*.name' => ['required', 'string', 'max:100'],

            // Photos — uploaded paths already on S3 (via presigned PUT).
            'attribute_image_paths' => ['nullable', 'array'],
            // Cap each section at 10 even for drafts (the min-5 total is only
            // enforced at final submit, not on partial drafts).
            'attribute_image_paths.*' => ['array', 'max:10'],
            'attribute_image_paths.*.*' => ['string', 'max:500'],
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

    /**
     * Only the place-column subset for the service — keep control fields
     * (draft_id, attributes, photo paths, cover) out so they don't get
     * shoved into the place row.
     *
     * @return array<string, mixed>
     */
    public function placeData(): array
    {
        $data = collect($this->validated())
            ->except(['draft_id', 'host_phone', 'attributes', 'attribute_image_paths', 'extra_image_paths', 'featured'])
            ->toArray();

        return $this->withCanonicalContent($data);
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
            'attribute_id' => (string) $a['attribute_id'],
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
        // An auto-save from a step before photos carries no photo fields at
        // all — return the syncPhotos() no-op shape so photos already saved
        // on the draft survive instead of being replaced with nothing.
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
