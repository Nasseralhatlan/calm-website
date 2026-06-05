<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\GeoStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCountryRequest extends FormRequest
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
            'country_code' => ['required', 'string', 'max:8', 'alpha_num', Rule::unique('countries', 'country_code')],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:32'],
            // `sometimes`: if the form omits status, skip validation and let the
            // DB default ('active') stand. The admin form always sends it; tests
            // and any programmatic clients can omit it without hitting an error.
            'status' => ['sometimes', new Enum(GeoStatus::class)],
        ];
    }
}
