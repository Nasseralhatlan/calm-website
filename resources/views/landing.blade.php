@extends('layouts.app')

@section('title', 'Calm — ' . __('hero_title'))

@section('meta')
    @php
        $ogTitle       = 'Calm — ' . __('hero_title');
        $ogDescription = __('brand_meta_description');
        $ogUrl         = url('/');
        $ogImageUrl    = url('/preview-image.png');
    @endphp
    <meta name="description" content="{{ $ogDescription }}">
    {{-- Open Graph (WhatsApp, Facebook, Slack, LinkedIn, etc.) --}}
    <meta property="og:site_name" content="Calm">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <meta property="og:image:secure_url" content="{{ $ogImageUrl }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $ogTitle }}">
    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImageUrl }}">
@endsection

@section('body')
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $arabicClass = $isRtl ? 'font-arabic' : '';
@endphp

<div class="min-h-screen flex flex-col bg-white">
    {{-- header --}}
    @php
        // The whole app authenticates through the `api` (JWT) guard now — the JWT cookie
        // is set on login and Laravel resolves the user from it for SSR Blade requests.
        $signedIn = auth('api')->user();
        $isHost = $signedIn?->isHost() ?? false;
        $profileRoute = $signedIn?->isAdmin() ? route('admin.dashboard') : route('profile');
        // Admins see "Dashboard"; regular users see "Profile" with a circular avatar.
        $profileLabel = $isRtl
            ? ($signedIn?->isAdmin() ? 'لوحة التحكم' : 'ملفي')
            : ($signedIn?->isAdmin() ? 'Dashboard' : 'Profile');
        $profileInitial = strtoupper(mb_substr($signedIn?->name ?: ($signedIn?->phone ?: '?'), 0, 1));
    @endphp
    <header class="w-full border-b border-[#ebebeb]">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto select-none" draggable="false">
            </a>

            <div class="flex items-center" style="gap: 6px;">
                @if($signedIn)
                    {{-- Profile-style button (avatar circle + label) --}}
                    <a href="{{ $profileRoute }}"
                       class="inline-flex items-center text-sm font-bold text-[#222] hover:bg-[#f7f7f7] transition-colors {{ $arabicClass }}"
                       style="padding: 6px 14px 6px 6px; border-radius: 999px; gap: 10px;">
                        <span class="flex items-center justify-center text-white font-bold"
                              style="width: 32px; height: 32px; border-radius: 50%; background-color: #222; font-size: 13px;">{{ $profileInitial }}</span>
                        <span>{{ $profileLabel }}</span>
                    </a>

                    {{-- Host CTA changes shape based on whether the user already hosts a place --}}
                    @if($isHost)
                        <a href="{{ route('user.places') }}"
                           class="inline-flex items-center text-sm font-bold text-white bg-[#F88379] hover:bg-[#f56b60] active:scale-[0.98] transition-all {{ $arabicClass }}"
                           style="padding: 10px 18px; border-radius: 14px; gap: 8px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"></path>
                            </svg>
                            <span>{{ $isRtl ? 'عرض أماكني' : 'View my places' }}</span>
                        </a>
                    @else
                        <a href="{{ route('host.places.create') }}"
                           class="inline-flex items-center text-sm font-bold text-white bg-[#F88379] hover:bg-[#f56b60] active:scale-[0.98] transition-all {{ $arabicClass }}"
                           style="padding: 10px 18px; border-radius: 14px; gap: 8px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12h14"></path>
                            </svg>
                            <span>{{ $isRtl ? 'كن مضيفاً' : 'Become a host' }}</span>
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center text-sm font-bold text-[#222] hover:bg-[#f7f7f7] transition-colors {{ $arabicClass }}"
                       style="padding: 10px 16px; border-radius: 14px; gap: 8px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        <span>{{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}</span>
                    </a>

                    <a href="{{ route('login', ['next' => '/host-register']) }}"
                       class="inline-flex items-center text-sm font-bold text-white bg-[#F88379] hover:bg-[#f56b60] active:scale-[0.98] transition-all {{ $arabicClass }}"
                       style="padding: 10px 18px; border-radius: 14px; gap: 8px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14"></path>
                        </svg>
                        <span>{{ $isRtl ? 'كن مضيفاً' : 'Become a host' }}</span>
                    </a>
                @endif

                <form method="POST" action="{{ url('/locale/' . ($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                    @csrf
                    <button type="submit"
                        style="border-radius: 14px;"
                        class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] px-4 py-3 transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                        {{ $locale === 'ar' ? 'English' : 'العربية' }}
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- HERO --}}
    <section class="px-6 sm:px-10 lg:px-20 py-6 sm:py-8">
        <div
            class="relative w-full overflow-hidden shadow-card min-h-[calc(100vh-12rem)] bg-[#0e3a44] hero-box"
        >
            <img
                src="/assets/landing/hero.jpeg"
                alt=""
                class="absolute inset-0 w-full h-full object-cover"
                draggable="false"
            >

            {{-- text directly on the photo, with shadow for legibility --}}
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-6" style="corner-shape: squircle">
                <h1 class="text-white font-black tracking-tight leading-[0.95] {{ $arabicClass }}"
                    style="font-size: clamp(44px, 9vw, 110px); text-shadow: 0 4px 24px rgba(0,0,0,0.35);">
                    {{ __('hero_title') }}
                </h1>
                <p class="mt-8 sm:mt-12 text-white font-medium max-w-3xl {{ $arabicClass }}"
                   style="font-size: clamp(20px, 3vw, 40px); line-height: 1.4; text-shadow: 0 2px 16px rgba(0,0,0,0.55);">
                    {{ __('hero_subtitle') }}
                </p>
            </div>
        </div>
    </section>

    {{-- ABOUT THE APP --}}
    <section class="px-6 sm:px-10 lg:px-20 py-20 sm:py-28">
        <div class="max-w-4xl mx-auto text-center">
            <img src="/assets/logo/logo.png"
                 alt="Calm"
                 class="mx-auto select-none"
                 style="height: clamp(80px, 12vw, 160px); width: auto;"
                 draggable="false">
            <h2 class="sr-only">{{ __('about_app_title') }}</h2>
            <p class="text-[#222] font-medium leading-relaxed {{ $arabicClass }}"
               style="margin-top: 20px; font-size: clamp(20px, 3vw, 32px);">
                {{ __('about_app_sub') }}
            </p>
            <p class="text-[#717171] leading-[1.85] max-w-2xl mx-auto {{ $arabicClass }}"
               style="margin-top: 28px; font-size: clamp(15px, 1.6vw, 18px);">
                {{ __('about_app_para') }}
            </p>
        </div>
    </section>

    {{-- FOR PROPERTY OWNERS — image on the start side --}}
    <section class="px-6 sm:px-10 lg:px-20" style="padding-top: 96px; padding-bottom: 96px;">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-32 items-center">
            {{-- mock app image in soft gray box --}}
            <div class="lg:order-1">
                <div class="w-full"
                     style="background-color: #fafafa; border-radius: 56px; corner-shape: squircle; padding: 56px 32px; min-height: 560px; display: flex; align-items: center; justify-content: center;">
                    <img src="/assets/mock-home.png"
                         alt=""
                         class="block h-auto select-none"
                         style="width: 100%; max-width: 420px;"
                         draggable="false"
                         loading="lazy">
                </div>
            </div>

            {{-- features (centered inside the column, text aligned to start) --}}
            <div class="lg:order-2 {{ $arabicClass }} flex flex-col items-center justify-center" style="min-height: 560px;">
                <div class="w-full" style="max-width: 460px;">
                <div class="text-sm font-bold uppercase tracking-wider" style="color: #F88379;">
                    {{ __('for_hosts_eyebrow') }}
                </div>
                <h2 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight text-[#222] leading-tight">
                    {{ __('for_hosts_title') }}
                </h2>

                <div class="flex flex-col" style="margin-top: 48px; gap: 48px;">
                    @php
                        $hostFeatures = [
                            ['emoji' => '📸', 'title' => 'host_f1_title', 'desc' => 'host_f1_desc'],
                            ['emoji' => '🕒', 'title' => 'host_f2_title', 'desc' => 'host_f2_desc'],
                            ['emoji' => '💎', 'title' => 'host_f3_title', 'desc' => 'host_f3_desc'],
                        ];
                    @endphp
                    @foreach($hostFeatures as $f)
                        <div class="flex items-start gap-5">
                            <div class="shrink-0 w-12 h-12 sm:w-14 sm:h-14 flex items-center justify-center text-2xl bg-[#fff5f4]"
                                 style="border-radius: 999px; corner-shape: squircle;">
                                {{ $f['emoji'] }}
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-xl font-bold text-[#222]">{{ __($f['title']) }}</h3>
                                <p class="mt-1.5 text-[15px] sm:text-base text-[#717171] leading-relaxed">{{ __($f['desc']) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- CTA --}}
                <a href="{{ route('login') }}"
                   class="inline-flex items-center font-bold text-white bg-[#F88379] hover:bg-[#f56b60] active:scale-[0.98] transition-all"
                   style="margin-top: 48px; padding: 16px 32px; gap: 10px; border-radius: 28px; corner-shape: squircle; box-shadow: 0 6px 14px rgba(248,131,121,0.3); font-size: 16px;">
                    <span>{{ __('host_cta') }}</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" @if($isRtl) style="transform: scaleX(-1);" @endif>
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
                </div>
            </div>
        </div>
    </section>

    {{-- FOR GUESTS — image on the opposite side --}}
    <section class="px-6 sm:px-10 lg:px-20" style="padding-top: 96px; padding-bottom: 96px;">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-32 items-center">
            {{-- features (centered inside the column, text aligned to start) --}}
            <div class="lg:order-1 {{ $arabicClass }} flex flex-col items-center justify-center" style="min-height: 560px;">
                <div class="w-full" style="max-width: 460px;">
                <div class="text-sm font-bold uppercase tracking-wider" style="color: #F88379;">
                    {{ __('for_guests_eyebrow') }}
                </div>
                <h2 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight text-[#222] leading-tight">
                    {{ __('for_guests_title') }}
                </h2>

                <div class="flex flex-col" style="margin-top: 48px; gap: 48px;">
                    @php
                        $guestFeatures = [
                            ['emoji' => '⭐', 'title' => 'guest_f1_title', 'desc' => 'guest_f1_desc'],
                            ['emoji' => '⚡', 'title' => 'guest_f2_title', 'desc' => 'guest_f2_desc'],
                            ['emoji' => '💎', 'title' => 'guest_f3_title', 'desc' => 'guest_f3_desc'],
                        ];
                    @endphp
                    @foreach($guestFeatures as $f)
                        <div class="flex items-start gap-5">
                            <div class="shrink-0 w-12 h-12 sm:w-14 sm:h-14 flex items-center justify-center text-2xl bg-[#fff5f4]"
                                 style="border-radius: 999px; corner-shape: squircle;">
                                {{ $f['emoji'] }}
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-xl font-bold text-[#222]">{{ __($f['title']) }}</h3>
                                <p class="mt-1.5 text-[15px] sm:text-base text-[#717171] leading-relaxed">{{ __($f['desc']) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                </div>
            </div>

            {{-- mock app image (search) in soft gray box --}}
            <div class="lg:order-2">
                <div class="w-full"
                     style="background-color: #fafafa; border-radius: 56px; corner-shape: squircle; padding: 56px 32px; min-height: 560px; display: flex; align-items: center; justify-content: center;">
                    <img src="/assets/mock-search.png"
                         alt=""
                         class="block h-auto select-none"
                         style="width: 100%; max-width: 420px;"
                         draggable="false"
                         loading="lazy">
                </div>
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="border-t border-[#ebebeb] px-6 sm:px-10 lg:px-20 py-14 bg-[#fafafa]">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-10">
                {{-- brand --}}
                <div>
                    <img src="/assets/logo/logo.png" alt="Calm" class="h-9 w-auto" draggable="false">
                    <p class="mt-4 text-sm text-[#717171] leading-relaxed max-w-xs {{ $arabicClass }}">
                        {{ __('footer_about') }}
                    </p>
                </div>

                {{-- contact --}}
                <div>
                    <h4 class="text-sm font-bold text-[#222] uppercase tracking-wider {{ $arabicClass }}">
                        {{ __('footer_contact') }}
                    </h4>
                    <ul class="mt-4 space-y-2 text-sm text-[#717171]">
                        {{-- Pulled from the admin-editable Settings (support_email / support_phone)
                             so the footer reflects whatever the admin has set without code changes. --}}
                        @if(! empty($supportEmail))
                            <li><a href="mailto:{{ $supportEmail }}" class="hover:text-[#222]" dir="ltr">{{ $supportEmail }}</a></li>
                        @endif
                        @if(! empty($supportPhone))
                            @php
                                // Build a tel: URL by stripping spaces; keep the display form readable.
                                $telHref = preg_replace('/\s+/', '', $supportPhone);
                                $waHref  = 'https://wa.me/'.ltrim(preg_replace('/\D+/', '', $supportPhone), '0');
                            @endphp
                            <li><a href="{{ $waHref }}" target="_blank" rel="noopener" class="hover:text-[#222]" dir="ltr">{{ $supportPhone }}</a></li>
                        @endif
                    </ul>
                </div>

                {{-- social --}}
                <div>
                    <h4 class="text-sm font-bold text-[#222] uppercase tracking-wider {{ $arabicClass }}">
                        {{ __('footer_follow') }}
                    </h4>
                    <div class="mt-4 flex items-center gap-3">
                        {{-- X / Twitter --}}
                        <a href="#" aria-label="X" class="w-10 h-10 rounded-full bg-white shadow-card flex items-center justify-center text-[#222] hover:bg-[#222] hover:text-white transition-colors">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M18.244 2H21l-6.52 7.452L22 22h-6.79l-5.32-6.946L3.78 22H1l7.005-8.006L1.78 2H8.69l4.79 6.34L18.244 2zm-1.19 18h1.86L7.06 4H5.11l11.944 16z"/></svg>
                        </a>
                        {{-- Instagram --}}
                        <a href="#" aria-label="Instagram" class="w-10 h-10 rounded-full bg-white shadow-card flex items-center justify-center text-[#222] hover:bg-[#222] hover:text-white transition-colors">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                        </a>
                        {{-- TikTok --}}
                        <a href="#" aria-label="TikTok" class="w-10 h-10 rounded-full bg-white shadow-card flex items-center justify-center text-[#222] hover:bg-[#222] hover:text-white transition-colors">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V9.42a8.16 8.16 0 0 0 4.77 1.52V7.49a4.85 4.85 0 0 1-1.84-.8z"/></svg>
                        </a>
                        {{-- Snapchat --}}
                        <a href="#" aria-label="Snapchat" class="w-10 h-10 rounded-full bg-white shadow-card flex items-center justify-center text-[#222] hover:bg-[#222] hover:text-white transition-colors">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M12.001 2c3.219 0 5.829 2.464 5.829 5.498 0 1.41-.078 2.51-.214 3.358.246.131.514.222.793.292.444.111.835.18 1.144.18.243 0 .51-.041.738-.183a.5.5 0 0 1 .69.196.5.5 0 0 1-.07.66c-.32.27-.74.434-1.15.524-.49.108-.99.176-1.49.21-.06.16-.13.32-.21.48.59.81 1.39 1.32 2.41 1.58.51.13.83.32.83.62 0 .53-1.04.92-2.62 1.07-.04.06-.09.26-.13.42-.07.27-.14.55-.31.7-.18.16-.42.18-.69.18-.34 0-.74-.05-1.21-.05-.74 0-1.05.11-1.41.36-.91.61-1.84 1.2-3.26 1.2-1.43 0-2.36-.59-3.27-1.2-.36-.25-.67-.36-1.41-.36-.47 0-.87.05-1.21.05-.27 0-.51-.02-.69-.18-.17-.15-.24-.43-.31-.7-.04-.16-.09-.36-.13-.42-1.58-.15-2.62-.54-2.62-1.07 0-.3.32-.49.83-.62 1.02-.26 1.82-.77 2.41-1.58-.08-.16-.15-.32-.21-.48-.5-.034-1-.102-1.49-.21-.41-.09-.83-.254-1.15-.524a.5.5 0 0 1-.07-.66.5.5 0 0 1 .69-.196c.228.142.495.183.738.183.309 0 .7-.069 1.144-.18.279-.07.547-.161.793-.292-.136-.848-.214-1.948-.214-3.358C6.172 4.464 8.782 2 12.001 2z"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-12 pt-6 border-t border-[#ebebeb] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[#717171]">
                <span class="{{ $arabicClass }}">© {{ date('Y') }} Calm. {{ __('footer_rights') }}</span>
                <span dir="ltr">khaled@calmapp.co</span>
            </div>
        </div>
    </footer>
</div>

<style>
    /* Hero box border-radius scales with viewport so it's not huge on mobile */
    .hero-box { border-radius: 28px; }
    @media (min-width: 640px) { .hero-box { border-radius: 60px; } }
    @media (min-width: 1024px) { .hero-box { border-radius: 100px; } }
</style>
@endsection
