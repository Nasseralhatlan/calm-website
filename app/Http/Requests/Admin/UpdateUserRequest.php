<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateUserRequest extends FormRequest
{
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
            'name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'birth_date' => ['nullable', 'date', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'role' => ['required', new Enum(UserRole::class)],
        ];
    }
}
