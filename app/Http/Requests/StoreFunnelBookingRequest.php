<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFunnelBookingRequest extends FormRequest
{
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
            // Fills a blank profile for customers who just registered via OTP.
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
