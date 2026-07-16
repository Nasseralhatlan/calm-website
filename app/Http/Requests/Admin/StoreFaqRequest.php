<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\FaqAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFaqRequest extends FormRequest
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
            'audience' => ['required', Rule::enum(FaqAudience::class)],
            // Arabic is the app's default language and therefore required;
            // English is optional (public page falls back to Arabic).
            'question_ar' => ['required', 'string', 'max:500'],
            'question_en' => ['nullable', 'string', 'max:500'],
            'answer_ar' => ['required', 'string', 'max:10000'],
            'answer_en' => ['nullable', 'string', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
