<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Place search filters. Public endpoint, so no authorization gate. `city_id` is
 * the only required field — every other filter is optional and narrows results.
 */
class SearchPlacesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'uuid', 'exists:cities,id'],
            'city_area_id' => ['sometimes', 'nullable', 'uuid', 'exists:city_areas,id'],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],

            'place_type_ids' => ['sometimes', 'nullable', 'array'],
            'place_type_ids.*' => ['uuid', 'exists:place_types,id'],

            'price_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'price_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'guests' => ['sometimes', 'nullable', 'integer', 'min:1'],

            'amenities' => ['sometimes', 'nullable', 'array'],
            'amenities.*' => ['uuid', 'exists:attributes,id'],

            // Optional stay window — both required together. Filters to places
            // free for the range.
            'check_in' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:today', 'required_with:check_out'],
            'check_out' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:check_in', 'required_with:check_in'],

            'sort' => ['sometimes', 'nullable', 'in:most_liked,price_asc,price_desc'],
        ];
    }

    /**
     * The validated filter set handed to PlaceService::search().
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->validated();
    }
}
