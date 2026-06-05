<?php

namespace App\Http\Controllers;

use App\Services\Place\SettingService;

class LandingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

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
            foreach (glob("{$dir}/*.{jpg,jpeg,png,webp,avif}", GLOB_BRACE) ?: [] as $f) {
                $base = strtolower(pathinfo($f, PATHINFO_FILENAME));
                if (isset($named[$base]) && $named[$base] === null) {
                    $named[$base] = '/assets/landing/'.basename($f);
                }
            }
            $heroImage = $named['hero'];
            $showcaseImage = $named['showcase'];

            // fall back to alphabetical ordering when names not used
            if (! $heroImage || ! $showcaseImage) {
                $all = glob("{$dir}/*.{jpg,jpeg,png,webp,avif}", GLOB_BRACE) ?: [];
                sort($all);
                $heroImage ??= isset($all[0]) ? '/assets/landing/'.basename($all[0]) : null;
                $showcaseImage ??= isset($all[1]) ? '/assets/landing/'.basename($all[1]) : null;
            }
        }

        // Pull support contact info from the admin-editable settings table.
        // Lookup is a single keyed-IN query — same service call rule as the
        // rest of the codebase.
        $support = $this->settings->byKeys(['support_email', 'support_phone']);

        return view('landing', [
            'heroImage'     => $heroImage,
            'showcaseImage' => $showcaseImage,
            'supportEmail'  => $support['support_email'] ?? null,
            'supportPhone'  => $support['support_phone'] ?? null,
        ]);
    }

    public function switchLocale(string $locale): \Illuminate\Http\RedirectResponse
    {
        if (! \in_array($locale, ['en', 'ar'], true)) {
            $locale = 'en';
        }

        return back()->withCookie(cookie()->forever('locale', $locale));
    }
}
