@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $menu = [
        ['faq', route('pages.faq'), $isRtl ? 'الأسئلة الشائعة' : 'FAQs'],
        ['chat', route('pages.support'), $isRtl ? 'تواصل معنا' : 'Contact us'],
        ['info', route('pages.about'), $isRtl ? 'عن كالم' : 'About Calm'],
        ['doc', route('pages.terms'), $isRtl ? 'الشروط والأحكام' : 'Terms & conditions'],
        ['shield', route('pages.privacy'), $isRtl ? 'سياسة الخصوصية' : 'Privacy policy'],
        ['doc', route('pages.cancellation'), $isRtl ? 'سياسة الإلغاء والاسترداد' : 'Cancellation & refund policy'],
        ['info', route('pages.community'), $isRtl ? 'معايير المجتمع' : 'Community standards'],
    ];
    $icons = [
        'globe' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3c2.5 2.6 3.8 5.7 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.7-3.8-9s1.3-6.4 3.8-9z"></path>',
        'faq' => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><line x1="12" y1="7.5" x2="12" y2="13"></line><circle cx="12" cy="16.5" r="0.4" fill="currentColor"></circle>',
        'chat' => '<path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z"></path><circle cx="9" cy="12" r="0.4" fill="currentColor"></circle><circle cx="13" cy="12" r="0.4" fill="currentColor"></circle><circle cx="17" cy="12" r="0.4" fill="currentColor"></circle>',
        'info' => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><line x1="12" y1="11" x2="12" y2="16.5"></line><circle cx="12" cy="7.5" r="0.4" fill="currentColor"></circle>',
        'doc' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"></path><path d="M14 3v5h5"></path>',
        'shield' => '<path d="M12 3l8 3v6c0 4.5-3.4 7.9-8 9-4.6-1.1-8-4.5-8-9V6l8-3z"></path>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>',
    ];
@endphp

@section('title', ($isRtl ? 'حسابى' : 'Account').' — Calm')

@section('body')
<div class="min-h-screen" style="background-color: #FBFBFB;">

    {{-- Fixed (sticky) page header --}}
    <header class="sticky top-0 z-30" style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="mx-auto w-full" style="max-width: 720px; padding: 18px 20px;">
            <h1 class="font-bold text-black {{ $fa }}" style="font-size: 20px; line-height: 1.3;">{{ $isRtl ? 'حسـابـى' : 'Account' }}</h1>
        </div>
    </header>

    <main class="mx-auto w-full" style="max-width: 720px; padding: 6px 20px 36px;">

        @if(! $me)
            {{-- Guest state (app parity): sign-in pitch + black pill, host
                 upsell with the coral CTA, then the same public menu. --}}
            <div style="padding-top: 10px;">
                <p class="{{ $fa }}" style="font-size: 15px; color: #AAAAAA; line-height: 1.8;">
                    {{ $isRtl ? 'سجل الدخول حتي تتمكن من تخطيط رحلتك القادمة او التحكم في وحداتك' : 'Sign in to plan your next trip or manage your units.' }}
                </p>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('calm-open-login'))"
                        class="calm-press calm-round inline-flex items-center justify-center font-bold text-white {{ $fa }}"
                        style="margin-top: 22px; padding: 15px 34px; border-radius: 999px; background-color: #1A1A1A; font-size: 16px;">
                    {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
                </button>
            </div>

            <div style="border-top: 1px solid #F1F1F1; margin-top: 30px; padding-top: 30px;">
                <h2 class="font-bold text-black {{ $fa }}" style="font-size: 20px;">{{ $isRtl ? 'لديك مكان للإيجار؟' : 'Have a place to rent?' }}</h2>
                <p class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA; margin-top: 6px; line-height: 1.7;">
                    {{ $isRtl ? 'أدرجه على كالم وابدأ باستقبال الحجوزات والكسب.' : 'List it on Calm and start receiving bookings.' }}
                </p>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('calm-open-login'))"
                        class="calm-press w-full font-bold text-white {{ $fa }}"
                        style="margin-top: 20px; padding: 18px; border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; background-color: #F88379; font-size: 17px;">
                    {{ $isRtl ? 'سجل كمضيف' : 'Become a host' }}
                </button>
            </div>
        @else
            {{-- Identity card --}}
            <div class="bg-white" style="margin-top: 22px; border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 50px rgba(0,0,0,0.05); padding: 24px;">
                <div class="flex items-start" style="gap: 16px;">
                    <span class="shrink-0 overflow-hidden flex items-center justify-center text-white font-bold"
                          style="width: 72px; height: 72px; border-radius: 22px; corner-shape: squircle; -webkit-corner-shape: squircle; background-color: #000; font-size: 26px;">
                        @if($me->avatar_url)
                            <img src="{{ $me->avatar_url }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(mb_substr($me->name ?: ($me->phone ?? '?'), 0, 1)) }}
                        @endif
                    </span>
                    <div class="flex-1 min-w-0" style="padding-top: 4px;">
                        <div class="flex items-center justify-between" style="gap: 8px;">
                            <span class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'الأسم' : 'Name' }}</span>
                            <span class="truncate" style="font-size: 14px; color: #AAAAAA;">{{ $me->name ?: '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between" style="gap: 8px; margin-top: 10px;">
                            <span class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'رقم الجوال' : 'Phone' }}</span>
                            <span dir="ltr" style="font-size: 14px; color: #AAAAAA;">{{ $me->phone ? '0'.$me->phone : '—' }}</span>
                        </div>
                    </div>
                </div>
                <div style="border-top: 1px solid #F1F1F1; margin-top: 18px; padding-top: 16px;">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'الجنس' : 'Gender' }}</span>
                        <span class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;">
                            {{ $me->gender === 'male' ? ($isRtl ? 'ذكر' : 'Male') : ($me->gender === 'female' ? ($isRtl ? 'أنثى' : 'Female') : '—') }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between" style="margin-top: 12px;">
                        <span class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'البريد الإلكترونى' : 'Email' }}</span>
                        <span class="truncate" dir="ltr" style="font-size: 14px; color: #AAAAAA;">{{ $me->email ?: '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between" style="margin-top: 12px;">
                        <span class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'تاريخ الميلاد' : 'Birth date' }}</span>
                        <span class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;">
                            {{ $me->birth_date ? $me->birth_date->locale($isRtl ? 'ar' : 'en')->translatedFormat('j F, Y') : '—' }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('profile') }}"
                   class="calm-press inline-flex items-center font-bold text-black {{ $fa }}"
                   style="margin-top: 18px; padding: 10px 22px; border-radius: 999px; border: 1px solid #E9E9E9; font-size: 13px; background: #fff;">
                    {{ $isRtl ? 'تعديل' : 'Edit' }}
                </a>
            </div>
        @endif

            {{-- Menu — shown to guests too (app parity); logout only when signed in --}}
            <div style="margin-top: 26px; border-top: 1px solid #F1F1F1; padding-top: 10px;">
                {{-- Locale row --}}
                <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                    @csrf
                    <button type="submit" class="calm-press flex items-center w-full" style="gap: 14px; padding: 15px 4px;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['globe'] !!}</svg>
                        <span class="font-semibold text-black {{ $locale === 'en' ? 'font-arabic' : '' }}" style="font-size: 15px;">{{ $locale === 'ar' ? 'English' : 'العربية' }}</span>
                    </button>
                </form>
                @foreach($menu as [$icon, $href, $label])
                    <a href="{{ $href }}" class="calm-press flex items-center" style="gap: 14px; padding: 15px 4px;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>
                        <span class="font-semibold text-black {{ $fa }}" style="font-size: 15px;">{{ $label }}</span>
                    </a>
                @endforeach
                @if($me)
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="calm-press flex items-center w-full" style="gap: 14px; padding: 15px 4px;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['logout'] !!}</svg>
                            <span class="font-semibold {{ $fa }}" style="font-size: 15px; color: #DC2626;">{{ $isRtl ? 'تسجيل الخروج' : 'Log out' }}</span>
                        </button>
                    </form>
                @endif
            </div>
    </main>

    {{-- Host / admin mode switch — floating pill above the tab bar (app parity) --}}
    @if($me && ($me->isHost() || $me->isAdmin()))
        <a href="{{ $me->isAdmin() ? route('admin.dashboard') : route('user.places') }}"
           class="calm-press fixed z-30 inline-flex items-center text-white font-bold {{ $fa }}"
           style="bottom: calc(86px + env(safe-area-inset-bottom, 0px)); left: 50%; transform: translateX(-50%); padding: 13px 22px; border-radius: 999px; gap: 8px; font-size: 14px; background-color: rgba(35,35,35,0.88); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); box-shadow: 0 10px 26px rgba(0,0,0,0.25); white-space: nowrap;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M7 16l-4-4 4-4M17 8l4 4-4 4"></path>
            </svg>
            <span>{{ $me->isAdmin() ? ($isRtl ? 'لوحة التحكم' : 'Dashboard') : ($isRtl ? 'التبديل إلى وضع المضيف' : 'Switch to host mode') }}</span>
        </a>
    @endif

    @include('partials._web_floating_nav')
    @include('partials._web_footer')
</div>
<style>[x-cloak] { display: none !important; }</style>
@endsection
