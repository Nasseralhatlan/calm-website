<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The occasions wizard payload. `occasion_type` is the one field we store as a
 * column (it's what we slice demand by); `details()` folds everything else into
 * the json blob. Validation stays deliberately loose on the option keys — the
 * wizard's catalogue changes while we test, and rejecting a lead because the
 * app shipped a new chip first would be the worst possible failure.
 */
class StoreOccasionRequest extends FormRequest
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
            'occasion_type' => ['required', 'string', 'max:64'],
            // Free text, expected when occasion_type is 'other'.
            'occasion_type_other' => ['nullable', 'string', 'max:80'],
            'needs' => ['nullable', 'array', 'max:32'],
            'needs.*' => ['string', 'max:64'],
            'needs_other' => ['nullable', 'string', 'max:300'],
            'guests' => ['required', 'integer', 'min:1', 'max:99999'],
            'venue_status' => ['nullable', 'string', 'in:have,help'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Everything the wizard collected beyond the occasion type, ready for the
     * `details` column. Empty answers are dropped so the blob stays readable in
     * the admin table.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        $v = $this->validated();

        return array_filter([
            'occasion_type_other' => $v['occasion_type_other'] ?? null,
            'needs' => $v['needs'] ?? [],
            'needs_other' => $v['needs_other'] ?? null,
            'guests' => (int) $v['guests'],
            'venue_status' => $v['venue_status'] ?? null,
            'notes' => $v['notes'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }
}
