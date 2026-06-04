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
        ];

        foreach (Place::PRICE_COLUMNS as $column) {
            $rules[$column] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * Only the place-column subset for the service — keep `draft_id` out so
     * it doesn't end up as a stray column.
     *
     * @return array<string, mixed>
     */
    public function placeData(): array
    {
        return collect($this->validated())
            ->except('draft_id')
            ->toArray();
    }
}
