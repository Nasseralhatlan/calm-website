<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a host blocking a date range from the availability manager.
 * Ownership of the place is enforced in the controller; this just guards the
 * date window itself — no blocking the past, end never before start.
 */
class StoreBlockingRequest extends FormRequest
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
            'start_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Just the columns PlaceAvailabilityService::block() persists.
     *
     * @return array{start_date: string, end_date: string, reason: string|null}
     */
    public function blockingData(): array
    {
        return [
            'start_date' => $this->string('start_date')->toString(),
            'end_date' => $this->string('end_date')->toString(),
            'reason' => $this->filled('reason') ? $this->string('reason')->toString() : null,
        ];
    }
}
