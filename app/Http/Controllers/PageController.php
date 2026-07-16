<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FaqAudience;
use App\Services\Content\FaqService;
use App\Services\Place\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Static public content pages (legal + about + support), available in Arabic
 * and English — each view renders both and picks the language from the current
 * locale (the `locale` cookie). Rendered via the shared `layouts.legal` chrome.
 */
class PageController extends Controller
{
    /**
     * Public support page (legal chrome). A signed-in user is sent to the
     * dashboard version so they keep the sidebar.
     */
    public function support(SettingService $settings): View|RedirectResponse
    {
        if (auth('api')->check()) {
            return redirect()->route('user.support');
        }

        $values = $settings->byKeys(['support_phone', 'support_email']);

        return view('pages.support', [
            'supportPhone' => $values['support_phone'] ?? null,
            'supportEmail' => $values['support_email'] ?? null,
        ]);
    }

    /** Support inside the user dashboard (with sidebar). */
    public function userSupport(SettingService $settings): View
    {
        $values = $settings->byKeys(['support_phone', 'support_email']);

        return view('user.support', [
            'supportPhone' => $values['support_phone'] ?? null,
            'supportEmail' => $values['support_email'] ?? null,
        ]);
    }

    /**
     * Public FAQ page (legal chrome, WebView-friendly). One tab per audience;
     * ?audience=guest|host picks the active tab (guest by default) so the app
     * can deep-link hosts straight to their questions.
     */
    public function faq(Request $request, FaqService $faqs): View
    {
        $audience = FaqAudience::tryFrom((string) $request->query('audience')) ?? FaqAudience::Guest;

        return view('pages.faq', [
            'audience' => $audience,
            'faqs' => $faqs->forAudience($audience),
        ]);
    }

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
