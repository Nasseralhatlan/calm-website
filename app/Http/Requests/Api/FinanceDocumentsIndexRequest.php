<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Listing the viewer's financial documents, optionally scoped to one booking
 * (the app's "View invoice" button on the booking detail screen).
 */
class FinanceDocumentsIndexRequest extends FormRequest
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
            'booking_id' => ['sometimes', 'uuid'],
        ];
    }

    public function bookingId(): ?string
    {
        $id = $this->string('booking_id')->toString();

        return $id !== '' ? $id : null;
    }
}
