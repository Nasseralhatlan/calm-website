<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin releases a host payout through Moyasar immediately, ahead of the stay
 * completing / the hold window closing. The note is the audit reason and is
 * kept on the booking.
 */
class ForcePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function note(): ?string
    {
        return trim($this->string('note')->toString()) ?: null;
    }
}
