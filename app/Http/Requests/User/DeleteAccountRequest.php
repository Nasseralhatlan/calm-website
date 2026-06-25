<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Account deletion is OTP-confirmed: the app first calls POST /auth/otp/request
 * to send a code, then DELETE /user with that code.
 */
class DeleteAccountRequest extends FormRequest
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
            'code' => ['required', 'string'],
        ];
    }
}
