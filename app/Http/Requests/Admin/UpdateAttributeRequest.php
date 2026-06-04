<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AttributePhotoRule;
use App\Enums\AttributeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $raw = $this->input('options_text');

        if (is_string($raw) && trim($raw) !== '') {
            $this->merge([
                'options' => collect(preg_split('/\r?\n/', $raw))
                    ->map(fn ($s) => trim((string) $s))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', 'exists:attribute_groups,id'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'question_ar' => ['nullable', 'string', 'max:500'],
            'question_en' => ['nullable', 'string', 'max:500'],
            'photo_rule' => ['sometimes', Rule::enum(AttributePhotoRule::class)],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:128'],
        ];
    }
}
