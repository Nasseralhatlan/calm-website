<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin settled a host payout by hand — a bank transfer from the company
 * account (outside Moyasar) or cash handed over — and records it with the
 * reference plus, optionally, why it was settled this way.
 */
class MarkPayoutPaidRequest extends FormRequest
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
            // Bank transfer reference, or the cash receipt/voucher number.
            'bank_reference' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'string', 'in:bank,cash'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function bankReference(): string
    {
        return trim($this->string('bank_reference')->toString());
    }

    /** @return 'bank'|'cash' */
    public function method(): string
    {
        return $this->string('method')->toString() === 'cash' ? 'cash' : 'bank';
    }

    public function note(): ?string
    {
        return trim($this->string('note')->toString()) ?: null;
    }
}
