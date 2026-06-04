<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends FormRequest
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
        $id = $this->route('setting')?->id;

        return [
            'key' => ['required', 'string', 'max:128', Rule::unique('settings', 'key')->ignore($id)],
            'value' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
