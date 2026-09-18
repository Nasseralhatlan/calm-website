<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\OccasionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One occasion lead as the guest sees it. `details` is echoed back verbatim so
 * the app can render whatever the wizard asked at the time — old rows keep
 * rendering after the catalogue changes.
 *
 * @mixin OccasionRequest
 */
class OccasionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ar = app()->getLocale() !== 'en';

        return [
            'id' => $this->id,
            'occasion_type' => $this->occasion_type,
            'details' => $this->details ?? [],
            'status' => $this->status?->value,
            'status_label' => $this->status?->label($ar),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
