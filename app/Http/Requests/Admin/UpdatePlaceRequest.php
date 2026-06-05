<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaceRequest extends FormRequest
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
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'place_type_id' => ['required', 'uuid', 'exists:place_types,id'],
            'city_area_id' => ['required', 'uuid', 'exists:city_areas,id'],
            'price' => ['required', 'integer', 'min:0'],
            'check_in_time' => ['required', 'string', 'max:8'],
            'check_out_time' => ['required', 'string', 'max:8'],
            'rules' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(PlaceStatus::class)],
            'review_status' => ['required', Rule::enum(PlaceReviewStatus::class)],
            // Admin can edit / clear the rejection feedback directly.
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ];

        // Add a min:0 integer rule for each day-specific price column.
        foreach (Place::PRICE_COLUMNS as $column) {
            $rules[$column] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }
}
