<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AttributePhotoRule;
use App\Enums\AttributeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        // The form sends `options` as a newline-separated text area;
        // split into an array if present.
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

        // An unchecked checkbox is absent from the payload — normalize to false.
        $this->merge(['is_highlighted' => $this->boolean('is_highlighted')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'uuid', 'exists:attribute_groups,id'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'question_ar' => ['nullable', 'string', 'max:500'],
            'question_en' => ['nullable', 'string', 'max:500'],
            'photo_rule' => ['sometimes', Rule::enum(AttributePhotoRule::class)],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'is_highlighted' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:128'],
        ];
    }
}
