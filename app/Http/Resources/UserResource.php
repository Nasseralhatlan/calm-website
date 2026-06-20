<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Public profile-picture URL to display (null when none set).
            'avatar_url' => $this->avatar_url,
            'gender' => $this->gender,
            'age' => $this->age,
            'birth_date' => $this->birth_date?->toDateString(),
            'phone' => $this->phone,
            'email' => $this->email,
            'country_id' => $this->country_id,
            // Payout bank account (null until the user adds one).
            'bank' => $this->bank,
            'bank_account' => $this->bank_account,
            'role' => $this->role?->value,
            // True iff the user has at least one place row, regardless of
            // status — drafts, rejected, approved all count. Frontend uses
            // this to flip the "Become a host" CTA to "My listings".
            'is_host' => $this->isHost(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
