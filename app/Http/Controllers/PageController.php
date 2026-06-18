<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Static public content pages (legal + about), available in Arabic and English
 * — each view renders both and picks the language from the current locale (the
 * `locale` cookie). Rendered via the shared `layouts.legal` chrome.
 */
class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function cancellation(): View
    {
        return view('pages.cancellation');
    }

    public function community(): View
    {
        return view('pages.community');
    }
}
