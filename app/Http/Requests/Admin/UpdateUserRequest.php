<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        // Empty form fields arrive as "" — treat optional ones as null so the
        // email/phone/date rules don't trip on a blank string.
        $optional = ['name', 'email', 'gender', 'birth_date', 'country_id'];

        $this->merge(
            collect($optional)
                ->filter(fn (string $f): bool => $this->input($f) === '')
                ->mapWithKeys(fn (string $f): array => [$f => null])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Phone is the login identifier — not editable from admin.
            'gender' => ['nullable', 'string', 'in:male,female'],
            'birth_date' => ['nullable', 'date', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'role' => ['required', new Enum(UserRole::class)],
        ];
    }
}
