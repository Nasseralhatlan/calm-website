<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Web;

use App\Http\Requests\Concerns\HasSaudiPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestLoginOtpRequest extends FormRequest
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
            // Country picked from the dropdown. Optional today — we only
            // support Saudi phones — but accepting it keeps the form payload
            // valid and lets the SMS layer expand to dial codes other than
            // +966 without another schema/validation change.
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'next' => ['nullable', 'string', 'starts_with:/'],
        ];
    }

    public function phone(): string
    {
        return $this->string('phone')->toString();
    }
}
