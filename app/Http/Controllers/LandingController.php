<?php

namespace App\Http\Controllers;

use App\Services\Web\WebHomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __construct(private readonly WebHomeService $home) {}

    /**
     * The browse home — app-style: pill search bar, quick place-type boxes,
     * curated lists, and an inline search-results grid. Replaces the old
     * marketing landing (its content lives on at /about).
     */
    public function index(): View
    {
        return view('home', $this->home->data(auth('api')->user()));
    }

    public function switchLocale(string $locale): RedirectResponse
    {
        if (! \in_array($locale, ['en', 'ar'], true)) {
            $locale = 'en';
        }

        return back()->withCookie(cookie()->forever('locale', $locale));
    }
}
