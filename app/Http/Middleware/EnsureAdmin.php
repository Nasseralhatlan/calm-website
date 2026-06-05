<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiResponse::error('Forbidden.', Response::HTTP_FORBIDDEN);
        }

        if (! $user) {
            return redirect()->route('login');
        }

        return redirect()->route('profile');
    }
}
