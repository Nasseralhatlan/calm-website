<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Web\RequestLoginOtpRequest;
use App\Http\Requests\Auth\Web\VerifyLoginOtpRequest;
use App\Services\Auth\OtpAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly OtpAuthService $auth) {}

    public function showLogin(Request $request): View
    {
        return view('auth.login', [
            'next' => $this->safeNext($request),
        ]);
    }

    public function requestOtp(RequestLoginOtpRequest $request): RedirectResponse
    {
        $this->auth->requestOtp(OtpType::Phone, $request->phone());

        $params = ['phone' => $request->phone()];

        if ($next = $this->safeNext($request)) {
            $params['next'] = $next;
        }

        return redirect()
            ->route('login.verify', $params)
            ->with('status', __('We sent a verification code to your phone.'));
    }

    public function showVerify(Request $request): View
    {
        return view('auth.verify', [
            'phone' => (string) $request->query('phone', ''),
            'next' => $this->safeNext($request),
        ]);
    }

    public function verifyOtp(VerifyLoginOtpRequest $request): RedirectResponse
    {
        $session = $this->auth->verifyOtpAndLogin(OtpType::Phone, $request->phone(), $request->code());

        if (! $session) {
            return back()
                ->withInput($request->only('phone'))
                ->withErrors(['otp' => __('Invalid or expired code.')]);
        }

        $destination = $this->safeNext($request)
            ?? ($session->user->isAdmin() ? route('admin.dashboard') : route('profile'));

        return redirect()->intended($destination)->withCookie($session->cookie);
    }

    public function logout(): RedirectResponse
    {
        $forgetCookie = $this->auth->logout();

        return redirect()->route('landing')->withCookie($forgetCookie);
    }

    /**
     * Return a sanitised `next` URL — must be a relative path on this host so
     * we can't be tricked into redirecting users to an external site after login.
     */
    private function safeNext(Request $request): ?string
    {
        $next = (string) $request->input('next', $request->query('next', ''));

        if ($next === '' || ! Str::startsWith($next, '/') || Str::startsWith($next, '//')) {
            return null;
        }

        return $next;
    }
}
