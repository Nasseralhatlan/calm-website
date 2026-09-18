<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\OccasionRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOccasionRequestStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(OccasionRequestStatus::class)],
        ];
    }
}
