<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if (! in_array($locale, ['en', 'ar'], true)) {
            $locale = str_starts_with($request->getPreferredLanguage(['en', 'ar']) ?? 'en', 'ar') ? 'ar' : 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
