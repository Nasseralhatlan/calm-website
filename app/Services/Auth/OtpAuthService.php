<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\OtpType;
use App\Models\Otp;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\User\UserService;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * High-level orchestrator for the OTP-based auth flow.
 *
 * Both Api/AuthController (mobile JSON) and Auth/LoginController (web Blade)
 * call into this single class so the business rules stay in one place —
 * the controllers shrink to "validate → call orchestrator → shape response".
 */
final class OtpAuthService
{
    public function __construct(
        private readonly UserService $users,
        private readonly OtpService $otp,
        private readonly AuthLoginService $login,
    ) {}

    /**
     * Find-or-create the user for this identifier, then dispatch an OTP.
     * The OtpService internally short-circuits if a usable OTP already exists.
     */
    public function requestOtp(OtpType $type, string $identifier): Otp
    {
        $user = $this->users->findOrCreateForOtp($type, $identifier);

        return $this->otp->issue($user, $type, $identifier);
    }

    /**
     * Verify the submitted code. On success, mint a JWT, build the cookie,
     * and return an AuthSession the caller can hand straight to a response.
     * On any failure (no user, wrong code, expired, locked) returns null.
     */
    public function verifyOtpAndLogin(OtpType $type, string $identifier, string $code): ?AuthSession
    {
        $user = $this->users->findByIdentifier($type, $identifier);

        if (! $user || ! $this->otp->verify($user, $type, $code)) {
            return null;
        }

        return $this->sessionFor($user->refresh());
    }

    /**
     * Mint a fresh JWT for an already-authenticated user.
     */
    public function refreshSessionFor(User $user): AuthSession
    {
        return $this->sessionFor($user);
    }

    /**
     * Invalidate the current JWT and return the forget-cookie the caller
     * should attach to the response so the browser drops it too.
     */
    public function logout(): Cookie
    {
        $this->login->invalidate();

        return $this->login->forgetCookie();
    }

    private function sessionFor(User $user): AuthSession
    {
        $tokens = $this->login->issue($user);

        return new AuthSession(
            user: $user,
            token: $tokens['token'],
            ttlSeconds: $tokens['ttl_seconds'],
            // Cookie lifetime mirrors the role-based token TTL.
            cookie: $this->login->buildCookie($tokens['token'], intdiv($tokens['ttl_seconds'], 60)),
        );
    }
}
