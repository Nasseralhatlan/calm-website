<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query params for the host calendar day detail. `date` is required; `place_id`,
 * when given, must belong to the authenticated host.
 */
class HostCalendarDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'place_id' => [
                'sometimes', 'nullable', 'uuid',
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($value !== null
                        && ! Place::query()->whereKey($value)->where('host_user_id', $this->user()->id)->exists()) {
                        $fail('The selected place is invalid.');
                    }
                },
            ],
        ];
    }
}
