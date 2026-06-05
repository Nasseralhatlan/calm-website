<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Result of a successful auth flow (login, verify-otp, refresh).
 *
 * Carries everything a controller needs to format its response — the user,
 * the JWT, the JWT's TTL in seconds, and the prebuilt httpOnly cookie.
 * That way the controller does NOT make a second service call to mint
 * the cookie itself — the orchestrator did it.
 */
final readonly class AuthSession
{
    public function __construct(
        public User $user,
        public string $token,
        public int $ttlSeconds,
        public Cookie $cookie,
    ) {}
}
