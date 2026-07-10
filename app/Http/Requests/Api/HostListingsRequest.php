<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Host "My listings" query. Optional `status` narrows the list to one
 * lifecycle tab — draft/pending_review/approved/rejected map to review_status;
 * `active` is the live-visibility flag on the status column.
 */
class HostListingsRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string', 'in:draft,pending_review,approved,rejected,active'],
        ];
    }
}
