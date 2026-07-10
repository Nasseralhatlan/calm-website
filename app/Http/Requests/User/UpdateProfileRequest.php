<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by both Api/UserController::update and web ProfileController::update.
 * The same rules apply regardless of which transport is calling.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalise the payout IBAN before validation: strip spaces and upper-case
     * so "sa03 8000 …" and "SA0380000000…" both pass and store consistently.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('bank_account')) {
            $iban = $this->input('bank_account');
            $this->merge([
                'bank_account' => is_string($iban) && $iban !== ''
                    ? strtoupper(preg_replace('/\s+/', '', $iban))
                    : null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getKey();

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            // Profile picture. Upload requires a multipart POST (PHP doesn't
            // parse multipart bodies on PATCH/PUT) — see the `POST /user` route.
            'avatar' => ['sometimes', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'age' => ['sometimes', 'nullable', 'integer', 'between:13,120'],
            // Frontend posts YYYY-MM-DD. Lower bound matches age:13.
            'birth_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($userId)],
            // App language — drives notification language (SMS/push) + the in-app
            // feed. Defaults to 'ar' at the DB level for new accounts.
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            // Payout bank — free-text bank name (informational).
            'bank' => ['sometimes', 'nullable', 'string', 'max:120'],
            // Saudi IBAN: "SA" + 2 check digits + 18-digit BBAN = 24 chars.
            'bank_account' => ['sometimes', 'nullable', 'string', 'regex:/^SA\d{22}$/'],
            // Name AS WRITTEN ON THE BANK ACCOUNT (may differ from the profile
            // name) — payout transfers use it as the beneficiary name.
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_account.regex' => __('Enter a valid Saudi IBAN (SA followed by 22 digits).'),
        ];
    }
}
