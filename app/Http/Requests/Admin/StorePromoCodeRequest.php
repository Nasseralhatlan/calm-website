<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PromoDiscountType;
use App\Models\PromoCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => PromoCode::normalize((string) $this->input('code', '')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPercentage = $this->input('discount_type') === PromoDiscountType::Percentage->value;

        return [
            'code' => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('promo_codes', 'code')->ignore($this->route('promoCode')),
            ],
            'discount_type' => ['required', Rule::enum(PromoDiscountType::class)],
            // Percent codes: up to 100% (the payment floor still applies).
            // Exceeding Calm's commission is ALLOWED — the form warns loudly.
            'discount_value' => ['required', 'numeric', 'gt:0', $isPercentage ? 'max:100' : 'max:1000000'],
            'max_discount_sar' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'min_stay_sar' => ['nullable', 'numeric', 'gt:0', 'max:10000000'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            // Calendar days, Asia/Riyadh (inclusive).
            'starts_at' => ['nullable', 'date_format:Y-m-d'],
            'ends_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
