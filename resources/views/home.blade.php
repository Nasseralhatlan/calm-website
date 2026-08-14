@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
    $isHost = $me?->isHost() ?? false;
@endphp

@section('title', $isRtl ? 'كالم — أماكن مختارة بعناية' : 'Calm — hand-picked stays')

@section('meta')
    <meta name="description" content="{{ __('brand_meta_description') }}">
    <meta property="og:title" content="{{ $isRtl ? 'كالم — أماكن مختارة بعناية' : 'Calm — hand-picked stays' }}">
    <meta property="og:description" content="{{ __('brand_meta_description') }}">
    <meta property="og:type" content="website">
@endsection

@section('body')
<div class="min-h-screen bg-white" x-data="calmHome()" x-init="init()"
     x-on:calm-search-apply.window="onApply($event.detail)"
     x-on:calm-filters-apply.window="onFiltersApply($event.detail)"
     x-on:calm-search-state.window="searchState = $event.detail">

    {{-- ══ Desktop header (≥1024px): logo left · search · language + profile
         dropdown. Mobile/tablet keep the app-style centered header below. ══ --}}
    @php
        $me = auth('api')->user();
        $meIsAdmin = $me?->isAdmin() ?? false;
        $meInitial = strtoupper(mb_substr($me?->name ?: ($me?->phone ?? '?'), 0, 1));
        $localeSwitchUrl = url('/locale/'.($locale === 'ar' ? 'en' : 'ar'));
    @endphp
    <header class="calm-show-desktop sticky top-0 z-40" x-show="mode === 'browse'"
            style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 0 50px rgba(0,0,0,0.05);">
        {{-- LTR row so the logo sits visually LEFT and the buttons RIGHT even in
             Arabic (the Calm wordmark is Latin — kept on the left by request). --}}
        <div class="mx-auto flex items-center justify-between" dir="ltr" style="max-width: 1240px; padding: 16px 24px; gap: 20px;">
            {{-- Logo left --}}
            <a href="{{ route('landing') }}" class="shrink-0 flex items-center">
                <img src="/assets/logo/logo.png" alt="Calm" style="height: 38px; width: auto;" draggable="false">
            </a>

            {{-- Center search --}}
            <button type="button" @click="$dispatch('calm-open-search')"
                    class="calm-press calm-round flex-1 flex items-center justify-center bg-white"
                    style="max-width: 440px; height: 52px; border-radius: 999px; border: 1px solid #F1F1F1; box-shadow: 0 0 50px rgba(0,0,0,0.05); gap: 9px;">
                <span dir="ltr" class="flex items-center" style="gap: 9px;">
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.125 13.125L11.25 11.25" stroke="black" stroke-width="1.5" stroke-linecap="round"/>
                        <ellipse cx="6.875" cy="7.49997" rx="5.625" ry="5.62497" stroke="black" stroke-width="1.5"/>
                    </svg>
                    <span class="text-[15px] font-semibold text-black {{ $fa }}">{{ $isRtl ? 'ابدء البحث' : 'Start searching' }}</span>
                </span>
            </button>

            {{-- Right: language toggle + sign-in / profile dropdown --}}
            <div class="shrink-0 flex items-center" style="gap: 6px;">
                <form method="POST" action="{{ $localeSwitchUrl }}" class="m-0">
                    @csrf
                    <button type="submit" style="padding: 9px 12px; border-radius: 999px;"
                            class="calm-press text-[14px] font-semibold text-black hover:bg-[#F5F5F5] transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                        {{ $locale === 'ar' ? 'English' : 'العربية' }}
                    </button>
                </form>

                @if($me)
                    <div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
                        <button type="button" @click="open = !open" :aria-expanded="open"
                                class="calm-press flex items-center bg-white hover:shadow-md transition-shadow"
                                style="gap: 9px; padding: 6px 6px; padding-inline-start: 14px; border: 1px solid #EBEBEB; border-radius: 999px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2" stroke-linecap="round">
                                <line x1="3" y1="7" x2="21" y2="7"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="17" x2="21" y2="17"></line>
                            </svg>
                            <span class="flex items-center justify-center text-white font-bold" style="width: 34px; height: 34px; border-radius: 50%; background-color: #000; font-size: 13px;">{{ $meInitial }}</span>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false"
                             dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute bg-white {{ $isRtl ? 'text-right' : 'text-left' }}"
                             style="top: calc(100% + 10px); right: 0; width: 256px; border-radius: 20px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 10px 44px rgba(0,0,0,0.16); padding: 8px; z-index: 50;">
                            <div style="padding: 10px 12px 12px;">
                                <div class="font-bold text-black truncate {{ $fa }}" style="font-size: 15px;">{{ $me->name ?: ($isRtl ? 'مرحباً' : 'Welcome') }}</div>
                                @if($me->phone)
                                    <div dir="ltr" class="{{ $isRtl ? 'text-right' : 'text-left' }} tabular-nums" style="font-size: 12px; color: #AAAAAA; margin-top: 3px;">0{{ $me->phone }}</div>
                                @endif
                            </div>
                            <div style="border-top: 1px solid #F1F1F1;"></div>
                            @php
                                $ddItems = [
                                    [route('user.trips'), $isRtl ? 'الحجوزات' : 'Reservations', '<rect x="3" y="5" width="18" height="16" rx="3"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="8" y1="2.5" x2="8" y2="6.5"></line><line x1="16" y1="2.5" x2="16" y2="6.5"></line>'],
                                    [route('user.favorites'), $isRtl ? 'المفضلة' : 'Wishlist', '<path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>'],
                                    [route('user.account'), $isRtl ? 'الملف الشخصي' : 'Profile', '<circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"></path>'],
                                ];
                            @endphp
                            <div style="padding: 6px 0;">
                                @foreach($ddItems as [$href, $label, $icon])
                                    <a href="{{ $href }}" class="calm-press flex items-center hover:bg-[#F7F7F7]"
                                       style="gap: 12px; padding: 11px 12px; border-radius: 12px;">
                                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                                        <span class="font-semibold text-black {{ $fa }}" style="font-size: 14px;">{{ $label }}</span>
                                    </a>
                                @endforeach
                                @if($isHost)
                                    <a href="{{ route('user.places') }}" class="calm-press flex items-center hover:bg-[#F7F7F7]" style="gap: 12px; padding: 11px 12px; border-radius: 12px;">
                                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12"></path><rect x="9" y="13" width="6" height="8"></rect></svg>
                                        <span class="font-semibold text-black {{ $fa }}" style="font-size: 14px;">{{ $isRtl ? 'وضع المضيف' : 'Host mode' }}</span>
                                    </a>
                                @endif
                                @if($meIsAdmin)
                                    <a href="{{ route('admin.dashboard') }}" class="calm-press flex items-center hover:bg-[#F7F7F7]" style="gap: 12px; padding: 11px 12px; border-radius: 12px;">
                                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                                        <span class="font-semibold text-black {{ $fa }}" style="font-size: 14px;">{{ $isRtl ? 'لوحة التحكم' : 'Dashboard' }}</span>
                                    </a>
                                @endif
                            </div>
                            <div style="border-top: 1px solid #F1F1F1;"></div>
                            <form method="POST" action="{{ route('logout') }}" class="m-0" style="padding: 6px 0 2px;">
                                @csrf
                                <button type="submit" class="calm-press flex items-center w-full hover:bg-[#F7F7F7]" style="gap: 12px; padding: 11px 12px; border-radius: 12px;">
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                    <span class="font-semibold {{ $fa }}" style="font-size: 14px; color: #DC2626;">{{ $isRtl ? 'تسجيل الخروج' : 'Log out' }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <button type="button" @click="$dispatch('calm-open-login')"
                            class="calm-press inline-flex items-center text-[14px] font-bold text-white hover:opacity-90 transition-opacity {{ $fa }}"
                            style="padding: 12px 24px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; background-color: #000;">
                        {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
                    </button>
                @endif
            </div>
        </div>
    </header>

    {{-- ══ App header: centered logo + «ابدء البحث» bar (sticky, blurred) ══ --}}
    <header class="calm-hide-desktop sticky top-0 z-30" x-show="mode === 'browse'"
            style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="mx-auto" style="max-width: 720px; padding: 14px 20px 14px;">
            <div class="flex items-center justify-center">
                <a href="{{ route('landing') }}"><img src="/assets/logo/logo.png" alt="Calm" style="height: 32px; width: auto;" draggable="false"></a>
            </div>
            <button type="button" @click="$dispatch('calm-open-search')"
                    class="calm-press calm-round flex items-center justify-center bg-white w-full"
                    style="margin-top: 14px; height: 54px; border-radius: 9999px; overflow: hidden; border: 1px solid #F1F1F1; box-shadow: 0 0 50px rgba(0,0,0,0.05); gap: 9px;">
                <span dir="ltr" class="flex items-center" style="gap: 9px;">
                    <svg width="16" height="16" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.125 13.125L11.25 11.25" stroke="black" stroke-width="1.5" stroke-linecap="round"/>
                        <ellipse cx="6.875" cy="7.49997" rx="5.625" ry="5.62497" stroke="black" stroke-width="1.5"/>
                    </svg>
                    <span class="text-[14px] font-semibold text-black {{ $fa }}">{{ $isRtl ? 'ابدء البحث' : 'Start searching' }}</span>
                </span>
            </button>
        </div>
    </header>

    <main class="mx-auto w-full calm-home-main" style="padding: 10px 0 36px;">

        {{-- ══ Browse mode ══ --}}
        <div x-show="mode === 'browse'">
            <div class="flex items-center justify-between" style="margin-top: 18px; gap: 10px; padding-inline: 20px;">
                <h1 class="font-bold text-black text-[22px] sm:text-[26px] {{ $fa }}" style="line-height: 1.25;">{{ $isRtl ? 'اهـلا بك' : 'Welcome' }}</h1>
                @if($isHost)
                    <a href="{{ route('user.places') }}"
                       class="calm-press lg:hidden inline-flex items-center text-white font-bold {{ $fa }}"
                       style="padding: 10px 16px; border-radius: 999px; gap: 6px; font-size: 12px; background-color: rgba(30,30,30,0.9);">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 16l-4-4 4-4M17 8l4 4-4 4"></path>
                        </svg>
                        {{ $isRtl ? 'وضع المضيف' : 'Host mode' }}
                    </a>
                @endif
            </div>

            {{-- Type boxes (app: 3 white squares) — hidden on desktop --}}
            <div class="grid grid-cols-3 lg:hidden" style="gap: 14px; margin-top: 22px; padding-inline: 20px;">
                @foreach($placeTypes->take(3) as $i => $t)
                    <button type="button" @click="$dispatch('calm-open-search', { typeId: @js($t->id) })"
                            class="calm-enter calm-press flex flex-col items-center justify-center bg-white min-w-0"
                            style="aspect-ratio: 1 / 0.92; border-radius: 26px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 10px; padding: 10px; box-shadow: 0 0 50px rgba(0,0,0,0.06); animation-delay: {{ 140 + $i * 50 }}ms;">
                        <span class="text-[26px] sm:text-[34px]" style="line-height: 1;">{{ $t->icon ?: '🏠' }}</span>
                        <span class="text-[12px] sm:text-[13px] font-bold text-black truncate w-full text-center {{ $fa }}">{{ $isRtl ? $t->name_ar : $t->name_en }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Curated lists — app rows: «{name} {icon}» + chevron circle --}}
            @foreach($lists as $li => $list)
                <section class="calm-enter" style="margin-top: 40px; animation-delay: {{ 260 + $li * 120 }}ms;"
                         aria-label="{{ $isRtl ? $list->name_ar : $list->name_en }}">
                    <div class="flex items-center justify-between" style="gap: 10px; padding-inline: 20px;">
                        <h2 class="font-bold text-black truncate text-[17px] sm:text-[19px] {{ $fa }}" style="line-height: 1.3;">
                            {{ $isRtl ? $list->name_ar : $list->name_en }}@if($list->icon)&nbsp;{{ $list->icon }}@endif
                        </h2>
                        {{-- See-all: chevron circle on mobile, text link on desktop --}}
                        <a href="{{ route('web.list', $list) }}" aria-label="{{ $isRtl ? 'عرض الكل' : 'See all' }}"
                           class="calm-press calm-round lg:hidden shrink-0 flex items-center justify-center text-black"
                           style="width: 36px; height: 36px; border-radius: 50%; background-color: #F5F5F5;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                                 style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                <path d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                        <a href="{{ route('web.list', $list) }}"
                           class="calm-press hidden lg:inline-flex items-center shrink-0 font-bold text-black hover:bg-[#F5F5F5] transition-colors {{ $fa }}"
                           style="gap: 5px; padding: 9px 16px; border-radius: 999px; font-size: 14px;">
                            <span>{{ $isRtl ? 'عرض الكل' : 'See all' }}</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
                                 style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                <path d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                    <div x-ref="list{{ $li }}" class="flex overflow-x-auto calm-hide-scroll lg:hidden" style="gap: 16px; margin-top: 4px; padding: 16px 20px 26px;">
                        @foreach($list->places->take(5) as $p)
                            @include('partials._web_place_card', ['p' => $p, 'compact' => true])
                        @endforeach

                        {{-- «عرض الكل» — animated stacked photos → the list page --}}
                        @php $seeAllCovers = $list->places->skip(5)->concat($list->places)->map(fn ($sp) => $sp->coverPhoto?->url ?? $sp->visiblePhotos()->first()?->url)->filter()->unique()->take(2)->values(); @endphp
                        <a href="{{ route('web.list', $list) }}"
                           x-data="{ shown: false }"
                           x-init="new IntersectionObserver((entries) => { shown = entries[0].isIntersecting; }, { root: $el.parentElement, threshold: 0.55 }).observe($el)"
                           :class="shown ? 'is-open' : ''"
                           class="calm-press calm-seeall shrink-0 flex flex-col items-center justify-center bg-white"
                           style="width: clamp(126px, 37vw, 172px); height: clamp(126px, 37vw, 172px); border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 14px; box-shadow: 0 0 50px rgba(0,0,0,0.08);">
                            <span class="relative block" style="width: 78px; height: 58px;">
                                @foreach($seeAllCovers as $ci => $cUrl)
                                    <img src="{{ $cUrl }}" alt="" loading="lazy"
                                         class="calm-seeall-img calm-seeall-img-{{ $ci }} object-cover">
                                @endforeach
                            </span>
                            <span class="flex items-center text-[13px] font-bold text-black {{ $fa }}" style="gap: 4px;">
                                <span>{{ $isRtl ? 'عرض الكل' : 'See all' }}</span>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
                                     style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                    <path d="M9 5l7 7-7 7"></path>
                                </svg>
                            </span>
                        </a>
                    </div>

                    {{-- Desktop: full grid — all items visible, none hidden in a
                         scroll — ending with the same animated «عرض الكل» deck
                         tile the mobile row uses. --}}
                    <div class="calm-home-grid">
                        @foreach($list->places->take(11) as $p)
                            @include('partials._web_place_card', ['p' => $p, 'compact' => false, 'cardRadius' => 32])
                        @endforeach

                        {{-- $seeAllCovers is built by the mobile row above (same
                             loop iteration) — reuse it, no extra photo lookups. --}}
                        <a href="{{ route('web.list', $list) }}"
                           x-data="{ shown: false }"
                           x-init="new IntersectionObserver((entries) => { shown = entries[0].isIntersecting; }, { threshold: 0.5 }).observe($el)"
                           :class="shown ? 'is-open' : ''"
                           class="calm-press calm-seeall flex flex-col items-center justify-center bg-white"
                           style="align-self: start; width: 100%; aspect-ratio: 1; border-radius: 32px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 14px; box-shadow: 0 0 50px rgba(0,0,0,0.08);">
                            <span class="relative block" style="width: 78px; height: 58px;">
                                @foreach($seeAllCovers as $ci => $cUrl)
                                    <img src="{{ $cUrl }}" alt="" loading="lazy"
                                         class="calm-seeall-img calm-seeall-img-{{ $ci }} object-cover">
                                @endforeach
                            </span>
                            <span class="flex items-center text-[13px] font-bold text-black {{ $fa }}" style="gap: 4px;">
                                <span>{{ $isRtl ? 'عرض الكل' : 'See all' }}</span>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
                                     style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                    <path d="M9 5l7 7-7 7"></path>
                                </svg>
                            </span>
                        </a>
                    </div>
                </section>
            @endforeach

            @if($lists->isEmpty())
                <div class="text-center {{ $fa }}" style="padding: 70px 20px; color: #AAAAAA;">
                    {{ $isRtl ? 'لا توجد أماكن معروضة بعد — عد قريباً.' : 'Nothing to show yet — check back soon.' }}
                </div>
            @endif
        </div>

        {{-- ══ Results mode — app: back + «أماكن فى {city} / N نتيجة» pill + filters ══ --}}
        <div x-show="mode === 'results'" x-cloak>
            <div class="sticky top-0 z-30 flex items-center justify-between"
                 style="gap: 10px; padding: 12px 20px; background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
                <button type="button" @click="clearSearch()" aria-label="{{ $isRtl ? 'رجوع' : 'Back' }}"
                        class="calm-press shrink-0 flex items-center justify-center text-black" style="width: 40px; height: 40px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                         style="{{ $isRtl ? '' : 'transform: scaleX(-1);' }}">
                        <path d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
                <button type="button" @click="$dispatch('calm-open-search')"
                        class="calm-press calm-round flex-1 min-w-0 bg-white text-center"
                        style="border-radius: 9999px; padding: 10px 20px; box-shadow: 0 0 50px rgba(0,0,0,0.08); max-width: 420px;">
                    <span class="block font-bold text-black truncate {{ $fa }}" style="font-size: 15px; line-height: 20px;"
                          x-text="'{{ $isRtl ? 'أماكن فى' : 'Places in' }} ' + ((searchState && searchState.cityName) || '…')"></span>
                    <span class="block tabular-nums {{ $fa }}" style="font-size: 12px; line-height: 16px; color: #AAAAAA;"
                          x-text="total + ' {{ $isRtl ? 'نتيجة' : 'results' }}'"></span>
                </button>
                <button type="button" @click="openFilters()" aria-label="{{ $isRtl ? 'الفلاتر' : 'Filters' }}"
                        class="calm-press shrink-0 flex items-center justify-center text-black" style="width: 40px; height: 40px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="4" y1="7" x2="20" y2="7"></line><circle cx="9" cy="7" r="2.4" fill="#fff"></circle>
                        <line x1="4" y1="16" x2="20" y2="16"></line><circle cx="15" cy="16" r="2.4" fill="#fff"></circle>
                    </svg>
                </button>
            </div>

            <div style="padding-inline: 20px;">
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 32px 24px; margin-top: 16px;">
                <template x-for="p in items" :key="p.id">
                    @include('partials._web_card_template')
                </template>
            </div>

            {{-- First-page loading — skeleton cards --}}
            <div x-show="loadingGrid && items.length === 0"
                 class="grid grid-cols-1 sm:grid-cols-2" style="gap: 32px 24px; margin-top: 24px;">
                @for($sk = 0; $sk < 2; $sk++)
                    <div>
                        <div class="calm-skeleton" style="border-radius: 24px; aspect-ratio: 1;"></div>
                        <div class="calm-skeleton" style="height: 14px; border-radius: 4px; width: 72%; margin-top: 12px;"></div>
                        <div class="calm-skeleton" style="height: 14px; border-radius: 4px; width: 50%; margin-top: 8px;"></div>
                        <div class="calm-skeleton" style="height: 14px; border-radius: 4px; width: 40%; margin-top: 8px;"></div>
                    </div>
                @endfor
            </div>

            {{-- Empty / error states --}}
            <div x-show="!loadingGrid && !gridError && items.length === 0" x-cloak
                 class="text-center {{ $fa }}" style="padding: 70px 0; color: #AAAAAA;">
                <div style="font-size: 34px; margin-bottom: 10px;">🔍</div>
                {{ $isRtl ? 'لا توجد نتائج مطابقة — جرّب تعديل البحث.' : 'No matching places — try adjusting your search.' }}
            </div>
            <div x-show="gridError" x-cloak class="text-center text-[#DC2626] {{ $fa }}" style="padding: 50px 0;">
                {{ $isRtl ? 'حدث خطأ أثناء البحث — أعد المحاولة.' : 'Something went wrong — please retry.' }}
            </div>

            {{-- Load more --}}
            <div class="text-center" style="margin-top: 32px;" x-show="hasMore && !gridError" x-cloak>
                <button type="button" @click="loadMore(searchParams())" :disabled="loadingGrid"
                        class="calm-press inline-flex items-center font-bold text-white hover:opacity-90 disabled:opacity-60 transition-opacity {{ $fa }}"
                        style="padding: 13px 34px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 8px; font-size: 14px; background-color: #000;">
                    <svg x-show="loadingGrid" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <path d="M21 12a9 9 0 1 1-6.2-8.56">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.9s" repeatCount="indefinite"/>
                        </path>
                    </svg>
                    <span>{{ $isRtl ? 'عرض المزيد' : 'Load more' }}</span>
                </button>
            </div>
            </div>
        </div>
    </main>

    {{-- Floating «ابدء بحث جديد» (browse mode, like the app) — mobile/tablet only --}}
    <div class="calm-hide-desktop">
        <button type="button" x-show="mode === 'browse'" @click="$dispatch('calm-open-search')"
                class="calm-press calm-round fixed z-30 inline-flex items-center text-white font-bold {{ $fa }}"
                style="bottom: calc(84px + env(safe-area-inset-bottom, 0px)); left: 50%; transform: translateX(-50%); padding: 10px 18px; border-radius: 9999px; gap: 7px; font-size: 13px; background-color: rgba(30,30,30,0.55); backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px); box-shadow: 0 8px 22px rgba(0,0,0,0.18); white-space: nowrap;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="9"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            <span>{{ $isRtl ? 'ابدء بحث جديد' : 'Start a new search' }}</span>
        </button>
    </div>

    @include('partials._web_search_modal', ['searchModalRedirect' => false])
    @include('partials._web_filters_modal')
    {{-- App tab bar — mobile/tablet only; on desktop nav lives in the header dropdown --}}
    <div class="calm-hide-desktop" x-show="mode === 'browse'">
        @include('partials._web_floating_nav')
    </div>
    @include('partials._web_footer')
</div>

@include('partials._web_grid_js')
<script>
    function calmHome() {
        return {
            ...calmGrid('/api/places/search'),

            mode: 'browse',
            searchState: null,
            // The last selection the search modal applied — drives load-more
            // params and the shareable URL. areaIds/typeIds are arrays.
            applied: null,
            // Advanced filters from the الفلاتر sheet.
            filters: { priceMin: null, priceMax: null, guests: null, amenityIds: [] },

            init() {
                const q = new URLSearchParams(window.location.search);
                if (q.get('city')) {
                    const list = (v) => (v || '').split(',').filter(Boolean);
                    this.applied = {
                        cityId: q.get('city'),
                        areaIds: list(q.get('area')),
                        typeIds: list(q.get('type')),
                        checkIn: /^\d{4}-\d{2}-\d{2}$/.test(q.get('in') || '') ? q.get('in') : null,
                        checkOut: /^\d{4}-\d{2}-\d{2}$/.test(q.get('out') || '') ? q.get('out') : null,
                    };
                    this.mode = 'results';
                    this.fetchPage(1, this.searchParams());
                }
            },

            onApply(selection) {
                this.applied = selection;
                // A fresh main search resets the advanced filters (app parity).
                this.filters = { priceMin: null, priceMax: null, guests: null, amenityIds: [] };
                this.mode = 'results';
                this.fetchPage(1, this.searchParams());
                this.syncUrl();
            },

            openFilters() {
                if (!this.applied) return;
                this.$dispatch('calm-open-filters', {
                    cityId: this.applied.cityId,
                    typeIds: this.applied.typeIds || [],
                    areaIds: this.applied.areaIds || [],
                    filters: this.filters,
                });
            },

            onFiltersApply(detail) {
                if (!this.applied) return;
                this.applied.typeIds = detail.typeIds;
                this.applied.areaIds = detail.areaIds;
                this.filters = {
                    priceMin: detail.priceMin, priceMax: detail.priceMax,
                    guests: detail.guests, amenityIds: detail.amenityIds,
                };
                // Keep the search sheet's chips in sync with the filter picks.
                this.$dispatch('calm-filters-sync', { typeIds: detail.typeIds, areaIds: detail.areaIds });
                this.fetchPage(1, this.searchParams());
                this.syncUrl();
            },

            searchParams() {
                const a = this.applied || {};
                const f = this.filters || {};
                const q = { city_id: a.cityId };
                // The API takes ONE area — send it only for a single pick.
                if ((a.areaIds || []).length === 1) q.city_area_id = a.areaIds[0];
                if ((a.typeIds || []).length) q['place_type_ids[]'] = a.typeIds;
                if (a.checkIn) { q.check_in = a.checkIn; q.check_out = a.checkOut || a.checkIn; }
                if (f.priceMin !== null && f.priceMin !== undefined) q.price_min = f.priceMin;
                if (f.priceMax !== null && f.priceMax !== undefined) q.price_max = f.priceMax;
                if (f.guests) q.guests = f.guests;
                if ((f.amenityIds || []).length) q['amenities[]'] = f.amenityIds;
                return q;
            },

            // «لـ 7 أيام» — inclusive day count like the app.
            stayLabel() {
                const a = this.applied;
                if (!a || !a.checkIn) return '';
                const days = Math.round((new Date(a.checkOut || a.checkIn) - new Date(a.checkIn)) / 86400000) + 1;
                return CALM_WEB.locale === 'ar' ? `لـ ${days} ${days === 1 ? 'يوم' : 'أيام'}` : `for ${days} ${days === 1 ? 'day' : 'days'}`;
            },

            clearSearch() {
                this.applied = null;
                this.items = []; this.total = 0; this.hasMore = false;
                this.mode = 'browse';
                window.history.replaceState({}, '', window.location.pathname);
                this.$dispatch('calm-search-reset');
            },

            syncUrl() {
                const a = this.applied;
                const q = new URLSearchParams();
                q.set('city', a.cityId);
                if ((a.areaIds || []).length) q.set('area', a.areaIds.join(','));
                if ((a.typeIds || []).length) q.set('type', a.typeIds.join(','));
                if (a.checkIn) { q.set('in', a.checkIn); q.set('out', a.checkOut || a.checkIn); }
                window.history.replaceState({}, '', `${window.location.pathname}?${q}`);
            },

        };
    }
</script>
<style>
    [x-cloak] { display: none !important; }
    .calm-hide-scroll { scrollbar-width: none; }
    .calm-hide-scroll::-webkit-scrollbar { display: none; }

    /* «عرض الكل» photos: stacked dead-center like a deck; when the tile
       scrolls into view at the row's end they deal out to each side. */
    .calm-seeall-img {
        position: absolute; top: 4px; left: 50%; width: 50px; height: 50px;
        margin-left: -25px; border: 3px solid #fff; border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.18);
        transition: transform 0.55s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .calm-seeall-img-0 { z-index: 1; }
    .calm-seeall-img-1 { z-index: 2; }
    .calm-seeall.is-open .calm-seeall-img-0 { transform: translate(-15px, 3px) rotate(-10deg); }
    .calm-seeall.is-open .calm-seeall-img-1 { transform: translate(15px, -3px) rotate(8deg); }
    @media (prefers-reduced-motion: reduce) {
        .calm-seeall-img { transition: none; }
        .calm-seeall.is-open .calm-seeall-img-0 { transform: translate(-15px, 3px) rotate(-10deg); }
        .calm-seeall.is-open .calm-seeall-img-1 { transform: translate(15px, -3px) rotate(8deg); }
    }

    /* ── Desktop-only home layout (≥1024px). Tablet + mobile keep the app view.
       These are self-contained display classes (not Tailwind lg:hidden) because
       Alpine's x-show writes an inline `display`, which beats a utility class —
       reverting to the stylesheet lets x-show and the breakpoint compose. ── */
    .calm-hide-desktop { display: block; }
    .calm-show-desktop { display: none; }
    .calm-home-main { max-width: 720px; }
    .calm-home-grid { display: none; }
    @media (min-width: 1024px) {
        .calm-hide-desktop { display: none; }
        .calm-show-desktop { display: block; }
        .calm-home-main { max-width: 1240px; }
        .calm-home-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
            gap: 34px 22px;
            padding: 20px 24px 8px;
        }
    }
</style>
@endsection
