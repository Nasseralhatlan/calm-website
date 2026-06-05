<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\GeoStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePlaceTypeRequest extends FormRequest
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
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', new Enum(GeoStatus::class)],
        ];
    }
}
