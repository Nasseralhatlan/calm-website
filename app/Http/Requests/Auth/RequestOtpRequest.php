<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\OtpType;
use App\Http\Requests\Concerns\HasSaudiPhoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'type' => ['required', 'string', Rule::enum(OtpType::class)],
            'identifier' => [
                'required',
                'string',
                Rule::when(
                    $this->input('type') === OtpType::Phone->value,
                    $this->saudiPhoneRule(),
                ),
                Rule::when(
                    $this->input('type') === OtpType::Email->value,
                    ['email:rfc', 'max:254'],
                ),
            ],
        ];
    }

    public function otpType(): OtpType
    {
        return OtpType::from($this->string('type')->toString());
    }

    public function identifier(): string
    {
        return $this->string('identifier')->toString();
    }
}
