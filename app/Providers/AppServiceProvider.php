<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PushDeliveryContract;
use App\Integrations\Push\ExpoPushDelivery;
use App\Integrations\Push\MockPushDelivery;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use PHPOpenSourceSaver\JWTAuth\Http\Parser\Cookies as JwtCookieParser;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Push transport — same driver-binding shape as the SMS contract
        // (see OtpServiceProvider). `mock` logs; `expo` hits Expo's push API.
        $this->app->bind(PushDeliveryContract::class, function (): PushDeliveryContract {
            return match (config('push.driver')) {
                'expo' => new ExpoPushDelivery(
                    accessToken: config('push.expo.access_token'),
                    timeout: (int) config('push.expo.timeout', 10),
                ),
                'mock', null => new MockPushDelivery,
                default => throw new RuntimeException('Unknown push driver: '.((string) config('push.driver'))),
            };
        });
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->registerRateLimiters();
        $this->registerJwtCookieParser();

        // Render every paginator with the app's squircle/brand styling.
        Paginator::defaultView('pagination::calm');
    }

    /**
     * Teach the JWT parser to also look in the calm_token cookie, so the same
     * `auth:api` guard authenticates both the mobile app (Authorization header)
     * and the browser (httpOnly cookie set on web login).
     *
     * Deferred to `booted()` so the JWT package's own service provider has
     * already registered the `tymon.jwt.parser` binding by the time we attach.
     */
    private function registerJwtCookieParser(): void
    {
        $this->app->booted(function ($app): void {
            $parser = (new JwtCookieParser((bool) $app['config']['jwt.decrypt_cookies']))
                ->setKey((string) $app['config']['jwt.cookie_key_name']);

            $app['tymon.jwt.parser']->addParser($parser);
        });
    }

    private function registerRateLimiters(): void
    {
        // OTP request + verify limiters used to live here. They were dropped
        // because OtpAuthService enforces the same protections at the
        // business-logic layer (per-identifier cooldown on send + per-OTP
        // attempt cap on verify). The HTTP-layer throttles were redundant
        // and complicated test setup.

        // Public, unauthenticated endpoints: 30/min per IP.
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Authenticated endpoints: 120/min per user (falls back to IP if no user).
        RateLimiter::for('authenticated', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
