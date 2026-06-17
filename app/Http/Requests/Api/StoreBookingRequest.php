<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Click book" payload. Same inclusive date window the quote endpoint uses,
 * plus a required party size. Availability + pricing are re-verified
 * server-side in BookingService — this only guards the shape of the input.
 */
class StoreBookingRequest extends FormRequest
{
    private const MAX_STAY_DAYS = 365;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:check_in'],
            'guests' => ['required', 'integer', 'min:1'],
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
                return;
            }

            if ($days > self::MAX_STAY_DAYS) {
                $validator->errors()->add('check_out', 'The stay cannot exceed '.self::MAX_STAY_DAYS.' days.');
            }
        });
    }
}
