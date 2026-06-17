<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The filters/facets page is scoped to one city. Public endpoint.
 */
class PlaceFiltersRequest extends FormRequest
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
        ];
    }
}
