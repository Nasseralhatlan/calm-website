<?php

namespace App\Http\Controllers;

use App\Services\Place\SettingService;
use Illuminate\Http\RedirectResponse;

class LandingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index()
    {
        // Pull support contact info from the admin-editable settings table.
        // Lookup is a single keyed-IN query — same service call rule as the
        // rest of the codebase.
        $support = $this->settings->byKeys(['support_email', 'support_phone']);

        return view('landing', [
            'supportEmail' => $support['support_email'] ?? null,
            'supportPhone' => $support['support_phone'] ?? null,
        ]);
    }

    public function switchLocale(string $locale): RedirectResponse
    {
        if (! \in_array($locale, ['en', 'ar'], true)) {
            $locale = 'en';
        }

        return back()->withCookie(cookie()->forever('locale', $locale));
    }
}
