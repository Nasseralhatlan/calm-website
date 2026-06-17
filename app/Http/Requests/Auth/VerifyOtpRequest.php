<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\HasSaudiPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Phone-only OTP verify. Mirrors {@see RequestOtpRequest} — when email
 * arrives, give it its own request class instead of bringing back `type`.
 */
class VerifyOtpRequest extends FormRequest
{
    use HasSaudiPhoneRule;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => $this->saudiPhoneRule(),
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    public function phone(): string
    {
        return $this->string('phone')->toString();
    }

    public function code(): string
    {
        return $this->string('otp')->toString();
    }
}
