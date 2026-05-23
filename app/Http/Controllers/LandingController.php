<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index()
    {
        $heroImage = null;
        $showcaseImage = null;

        $dir = public_path('assets/landing');
        if (is_dir($dir)) {
            // prefer specific names if present
            $named = [
                'hero'     => null,
                'showcase' => null,
            ];
            foreach (glob($dir.'/*.{jpg,jpeg,png,webp,avif}', GLOB_BRACE) ?: [] as $f) {
                $base = strtolower(pathinfo($f, PATHINFO_FILENAME));
                if (isset($named[$base]) && $named[$base] === null) {
                    $named[$base] = '/assets/landing/'.basename($f);
                }
            }
            $heroImage = $named['hero'];
            $showcaseImage = $named['showcase'];

            // fall back to alphabetical ordering when names not used
            if (! $heroImage || ! $showcaseImage) {
                $all = glob($dir.'/*.{jpg,jpeg,png,webp,avif}', GLOB_BRACE) ?: [];
                sort($all);
                $heroImage ??= isset($all[0]) ? '/assets/landing/'.basename($all[0]) : null;
                $showcaseImage ??= isset($all[1]) ? '/assets/landing/'.basename($all[1]) : null;
            }
        }

        return view('landing', [
            'heroImage'     => $heroImage,
            'showcaseImage' => $showcaseImage,
        ]);
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['en', 'ar'], true)) {
            $locale = 'en';
        }

        return back()->withCookie(cookie()->forever('locale', $locale));
    }
}
