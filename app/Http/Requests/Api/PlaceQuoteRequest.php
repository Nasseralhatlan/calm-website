<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Stay window + party size for GET /api/places/{place}/quote. Public endpoint
 * (the checkout page is pre-booking), so no authorization gate. Both dates are
 * inclusive; check-in can't be in the past and the stay is capped at one year.
 */
class PlaceQuoteRequest extends FormRequest
{
    /** Longest stay we'll price in one call — bounds the day-by-day expansion. */
    private const MAX_STAY_DAYS = 365;

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
            'check_in' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:check_in'],
            'guests' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $checkIn = $this->input('check_in');
            $checkOut = $this->input('check_out');

            if (! is_string($checkIn) || ! is_string($checkOut)) {
                return;
            }

            try {
                $days = CarbonImmutable::parse($checkIn)->diffInDays(CarbonImmutable::parse($checkOut)) + 1;
            } catch (\Throwable) {
                return; // malformed dates are already caught by the rules above
            }

            if ($days > self::MAX_STAY_DAYS) {
                $validator->errors()->add('check_out', 'The stay cannot exceed '.self::MAX_STAY_DAYS.' days.');
            }
        });
    }
}
