<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Single source of truth for issuing and revoking JWTs.
 *
 * Both the web LoginController (which redirects + sets a cookie) and the API
 * AuthController (which returns the token in JSON) flow through here so the
 * mobile app and the browser end up holding the same token, validated by the
 * same `api` guard.
 */
final class AuthLoginService
{
    /**
     * Mint a fresh JWT for the user. Lifetime is role-based: admins get the
     * short `jwt.ttl` (their dashboard sessions should die fast), everyone
     * else — guests and hosts in the app — gets the months-long
     * `jwt.user_ttl`. Set explicitly on every mint so issuances can't leak
     * a TTL into each other within one request.
     *
     * @return array{token: string, ttl_seconds: int}
     */
    public function issue(User $user): array
    {
        $ttlMinutes = $this->ttlMinutesFor($user);

        JWTAuth::factory()->setTTL($ttlMinutes);
        $token = JWTAuth::fromUser($user);

        return [
            'token' => $token,
            'ttl_seconds' => $ttlMinutes * 60,
        ];
    }

    /**
     * Build the httpOnly cookie that carries the JWT to the browser.
     * The cookie's lifetime mirrors the token's role-based TTL so they
     * expire together.
     */
    public function buildCookie(string $token, ?int $ttlMinutes = null): SymfonyCookie
    {
        return Cookie::make(
            (string) config('jwt.cookie_key_name'),
            $token,
            $ttlMinutes ?? $this->ttlMinutes(),
            '/',
            null,
            (bool) config('jwt.cookie_secure'),
            true,
            false,
            (string) config('jwt.cookie_same_site'),
        );
    }

    /**
     * Build the cookie that clears the JWT cookie on logout.
     */
    public function forgetCookie(): SymfonyCookie
    {
        return Cookie::forget((string) config('jwt.cookie_key_name'));
    }

    /**
     * Invalidate the currently-authenticated token (logout).
     */
    public function invalidate(): void
    {
        try {
            auth('api')->logout();
        } catch (\Throwable) {
            // No active token — nothing to revoke.
        }
    }

    public function ttlMinutesFor(User $user): int
    {
        return $user->isAdmin()
            ? $this->ttlMinutes()
            : (int) (config('jwt.user_ttl') ?? 60 * 24 * 180);
    }

    public function ttlMinutes(): int
    {
        return (int) (config('jwt.ttl') ?? 60);
    }

    public function ttlSeconds(): int
    {
        return $this->ttlMinutes() * 60;
    }
}
