<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['sometimes', 'nullable', Rule::in(['ios', 'android'])],
            // Lets the app set the user's preferred notification language.
            'locale' => ['sometimes', 'nullable', Rule::in(['ar', 'en'])],
        ];
    }
}
