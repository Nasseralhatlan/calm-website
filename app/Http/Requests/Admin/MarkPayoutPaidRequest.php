<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin settled a host payout by hand (bank transfer from the company account,
 * outside Moyasar) and records it with the bank's transfer reference.
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
            'bank_reference' => ['required', 'string', 'max:100'],
        ];
    }

    public function bankReference(): string
    {
        return trim($this->string('bank_reference')->toString());
    }
}
