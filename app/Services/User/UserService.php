<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\OtpType;
use App\Enums\UserRole;
use App\Models\User;

/**
 * Single source of truth for user lookup, creation, and profile updates.
 *
 * Both transports (web Blade redirects, API JSON responses) call into this
 * service so the same business rules apply — column choice per OTP channel,
 * default role on auto-create, allowed-attribute filtering on update.
 */
final class UserService
{
    /**
     * Find a user by the channel they're authenticating through.
     */
    public function findByIdentifier(OtpType $type, string $identifier): ?User
    {
        return User::query()
            ->where($this->columnFor($type), $identifier)
            ->first();
    }

    /**
     * Find a user by their identifier, creating an unverified shell if missing.
     * Used by the OTP request flow on first contact.
     */
    public function findOrCreateForOtp(OtpType $type, string $identifier): User
    {
        return User::query()->firstOrCreate(
            [$this->columnFor($type) => $identifier],
            ['role' => UserRole::User->value],
        );
    }

    /**
     * Apply a validated attribute set to the user.
     * Only mass-assigned columns make it through; role is never editable here.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function update(User $user, array $attrs): User
    {
        unset($attrs['role'], $attrs['phone_verified_at'], $attrs['email_verified_at']);

        $user->fill($attrs)->save();

        return $user->refresh();
    }

    private function columnFor(OtpType $type): string
    {
        return $type === OtpType::Phone ? 'phone' : 'email';
    }
}
