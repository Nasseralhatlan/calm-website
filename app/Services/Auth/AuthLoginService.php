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
     * Mint a fresh JWT for the user.
     *
     * @return array{token: string, ttl_seconds: int}
     */
    public function issue(User $user): array
    {
        $token = JWTAuth::fromUser($user);

        return [
            'token' => $token,
            'ttl_seconds' => $this->ttlSeconds(),
        ];
    }

    /**
     * Build the httpOnly cookie that carries the JWT to the browser.
     * The cookie's lifetime mirrors the JWT TTL so they expire together.
     */
    public function buildCookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            (string) config('jwt.cookie_key_name'),
            $token,
            $this->ttlMinutes(),
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

    public function ttlMinutes(): int
    {
        return (int) (config('jwt.ttl') ?? 60);
    }

    public function ttlSeconds(): int
    {
        return $this->ttlMinutes() * 60;
    }
}
