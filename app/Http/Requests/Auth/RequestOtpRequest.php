<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\HasSaudiPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Phone-only OTP request. Email login isn't exposed yet — when it is, add a
 * dedicated request class rather than re-introducing a `type` field here, so
 * the API contract stays explicit.
 */
class RequestOtpRequest extends FormRequest
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
        ];
    }

    public function phone(): string
    {
        return $this->string('phone')->toString();
    }
}
