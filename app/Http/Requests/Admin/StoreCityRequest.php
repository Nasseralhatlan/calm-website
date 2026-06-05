<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\GeoStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCityRequest extends FormRequest
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
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'status' => ['sometimes', new Enum(GeoStatus::class)],
        ];
    }
}
