<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional calendar-window bounds for GET /api/places/{place}/unavailable-dates.
 * Both are optional — the service defaults to [today, today + 12 months] when
 * omitted. Public endpoint, so no authorization gate.
 */
class PlaceUnavailableDatesRequest extends FormRequest
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
            'from' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            // `after_or_equal:from` only fires when `from` is also present; when
            // it isn't, the service clamps a backwards window to a single day.
            'to' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
