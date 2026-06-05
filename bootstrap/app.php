<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
        $middleware->trustProxies(at: '*');

        // The JWT cookie holds the raw token — Laravel must NOT encrypt it
        // or the JWT package's Cookies parser will fail to decode it on next request.
        // bootstrap/app.php runs before the config service is bound, so we read env() directly.
        $middleware->encryptCookies(except: [
            (string) env('JWT_COOKIE_NAME', 'calm_token'),
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);

        // When an unauthenticated request hits an auth-protected route, send the
        // browser to /login (the default `route('login')` named target works,
        // but make it explicit).
        $middleware->redirectGuestsTo(fn () => route('login'));

        // When an *already authenticated* user hits a `guest`-only route (e.g. /login),
        // bounce them to their dashboard. Admin → admin panel, user → profile.
        $middleware->redirectUsersTo(function (Request $request): string {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            return $user?->isAdmin() ? route('admin.dashboard') : route('profile');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $isApi($request));

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                data: ['errors' => $e->errors()],
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error('Forbidden.', Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error('Too many requests.', Response::HTTP_TOO_MANY_REQUESTS);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error('Not found.', Response::HTTP_NOT_FOUND);
        });

        // Catch-all for any other HTTP exception so it goes through the envelope too.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                message: $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                status: $e->getStatusCode(),
            );
        });
    })->create();
