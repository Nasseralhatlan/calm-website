<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin marks a host payout as settled (after the manual bank transfer) or
 * reverts a mistaken one. The optional reference is the bank-transfer id the
 * admin wants on record.
 */
class UpdatePayoutStatusRequest extends FormRequest
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
            'payout_status' => ['required', 'string', 'in:paid,not_paid'],
            'payout_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
