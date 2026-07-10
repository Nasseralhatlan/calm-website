<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Host "Transfers" ledger query. `state` optionally narrows to one payout
 * state — the same vocabulary Booking::payoutState() emits.
 */
class HostPayoutsIndexRequest extends FormRequest
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
            'state' => ['sometimes', Rule::in([
                'paid', 'processing', 'upcoming', 'awaiting_completion', 'awaiting_bank_details', 'failed',
            ])],
        ];
    }

    public function state(): ?string
    {
        $state = $this->string('state')->toString();

        return $state !== '' ? $state : null;
    }
}
