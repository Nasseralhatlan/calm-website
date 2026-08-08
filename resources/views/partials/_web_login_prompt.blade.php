{{-- Inline sign-in prompt for gated tabs (app parity: the tab renders, the
     content asks to sign in). Expects $promptTitle / $promptSubtitle. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp
<div class="text-center" style="padding: 90px 24px;">
    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 22px; line-height: 28px;">{{ $promptTitle }}</h2>
    <p class="{{ $fa }}" style="font-size: 14px; margin-top: 8px; color: #AAAAAA;">{{ $promptSubtitle }}</p>
    <a href="{{ route('login', ['next' => request()->getRequestUri()]) }}"
       class="calm-press inline-flex items-center justify-center font-bold text-white {{ $fa }}"
       style="margin-top: 24px; padding: 15px 48px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; font-size: 15px; background-color: #000;">
        {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
    </a>
</div>
