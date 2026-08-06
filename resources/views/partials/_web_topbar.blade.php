{{-- Guest web top bar — Airbnb-style: logo · segmented search bar
     (city | when | type | area + coral circle button) · host CTA + locale +
     sign-in/avatar. Header size matches the place-page photo-tour header
     (h-20, white 0.7 + blur). The bar is borderless with a strong shadow;
     each segment is a pill of its own.

     With $searchPill = true the bar opens the reusable wizard
     (partials/_web_search_modal must be on the page); segment labels stay
     live via the modal's 'calm-search-state' broadcasts. Otherwise the bar
     is a static link back to the home search. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
    $searchPill = $searchPill ?? false;
    $profileRoute = $me?->isAdmin() ? route('admin.dashboard') : route('profile');
    $profileInitial = strtoupper(mb_substr($me?->name ?: ($me?->phone ?? '?'), 0, 1));

    $anyCity = $isRtl ? 'أي مدينة' : 'Anywhere';
    $anyTime = $isRtl ? 'أي وقت' : 'Anytime';
    $anyType = $isRtl ? 'أي نوع' : 'Any type';
    $anyArea = $isRtl ? 'أي حي' : 'Any area';

    $hostCta = null;
    if ($me === null) {
        $hostCta = [route('login', ['next' => '/host-register']), $isRtl ? 'كن مضيفاً' : 'Become a host'];
    } elseif ($me->isHost()) {
        $hostCta = [route('user.places'), $isRtl ? 'أماكني' : 'My places'];
    } elseif (! $me->isAdmin()) {
        $hostCta = [route('host.places.create'), $isRtl ? 'كن مضيفاً' : 'Become a host'];
    }

    $barShadow = 'box-shadow: 0 6px 20px rgba(0,0,0,0.13);';
    $sepStyle = 'width: 1px; height: 26px; background: #F3F4F6;';
@endphp
<header class="sticky top-0 z-40 border-b border-[#ebebeb]"
        style="background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
    <div class="mx-auto flex items-center justify-between" style="max-width: 1200px; padding: 0 16px; height: 88px; gap: 12px;">
        <a href="{{ route('landing') }}" class="shrink-0 flex items-center">
            <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
        </a>

        {{-- Segmented search bar (desktop; mobile gets its own row below) --}}
        <div class="hidden md:flex flex-1 justify-center min-w-0">
            @if($searchPill)
                <div x-data="calmTopSearch()" x-on:calm-search-state.window="set($event.detail)"
                     class="flex items-center bg-white"
                     style="border-radius: 999px; height: 66px; padding: 0 9px; gap: 2px; {{ $barShadow }}">
                    <button type="button" @click="$dispatch('calm-open-search')"
                            class="calm-press flex items-center hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                            style="gap: 8px; padding: 0 22px; height: 50px; border-radius: 999px;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"></path>
                        </svg>
                        <span class="text-[14px] font-bold text-[#1A1A1A] whitespace-nowrap" x-text="city || @js($anyCity)"></span>
                    </button>
                    <span class="shrink-0" style="{{ $sepStyle }}"></span>
                    <button type="button" @click="$dispatch('calm-open-search', { step: 'when' })"
                            class="calm-press hover:bg-[#f7f7f7] transition-colors text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}"
                            style="padding: 0 22px; height: 50px; border-radius: 999px;" x-text="when || @js($anyTime)"></button>
                    <span class="shrink-0" style="{{ $sepStyle }}"></span>
                    <button type="button" @click="$dispatch('calm-open-search', { step: 'type' })"
                            class="calm-press hover:bg-[#f7f7f7] transition-colors text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}"
                            style="padding: 0 22px; height: 50px; border-radius: 999px;" x-text="type || @js($anyType)"></button>
                    <span class="shrink-0" style="{{ $sepStyle }}"></span>
                    <button type="button" @click="$dispatch('calm-open-search', { step: 'area' })"
                            class="calm-press hover:bg-[#f7f7f7] transition-colors text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}"
                            style="padding: 0 22px; height: 50px; border-radius: 999px;" x-text="area || @js($anyArea)"></button>
                    <button type="button" @click="$dispatch('calm-open-search')" aria-label="{{ $isRtl ? 'بحث' : 'Search' }}"
                            class="calm-press shrink-0 flex items-center justify-center text-white bg-[#F88379] hover:bg-[#E66E64] transition-colors"
                            style="width: 48px; height: 48px; border-radius: 50%; margin-inline-start: 6px;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                        </svg>
                    </button>
                </div>
            @else
                <a href="{{ route('landing') }}"
                   class="calm-press flex items-center bg-white"
                   style="border-radius: 999px; height: 66px; padding: 0 9px; gap: 2px; {{ $barShadow }}">
                    <span class="flex items-center text-[14px] font-bold text-[#1A1A1A] whitespace-nowrap {{ $fa }}" style="gap: 8px; padding: 0 22px;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"></path>
                        </svg>
                        {{ $anyCity }}
                    </span>
                    <span class="shrink-0" style="{{ $sepStyle }}"></span>
                    <span class="text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}" style="padding: 0 22px;">{{ $anyTime }}</span>
                    <span class="shrink-0" style="{{ $sepStyle }}"></span>
                    <span class="text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}" style="padding: 0 22px;">{{ $anyType }}</span>
                    <span class="shrink-0" style="{{ $sepStyle }}"></span>
                    <span class="text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}" style="padding: 0 22px;">{{ $anyArea }}</span>
                    <span class="shrink-0 flex items-center justify-center text-white bg-[#F88379]"
                          style="width: 48px; height: 48px; border-radius: 50%; margin-inline-start: 6px;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                        </svg>
                    </span>
                </a>
            @endif
        </div>

        <div class="flex items-center shrink-0" style="gap: 4px;">
            @if($hostCta)
                <a href="{{ $hostCta[0] }}"
                   class="hidden lg:inline-flex text-[14px] font-semibold text-[#1A1A1A] hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                   style="padding: 10px 14px; border-radius: 999px;">{{ $hostCta[1] }}</a>
            @endif
            <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                @csrf
                <button type="submit" style="border-radius: 999px; padding: 9px 14px;"
                        class="text-[13px] font-semibold text-[#1A1A1A] hover:bg-[#f7f7f7] transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                    {{ $locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </form>

            @if($me)
                <a href="{{ $profileRoute }}" aria-label="{{ $isRtl ? 'حسابي' : 'Account' }}"
                   class="calm-press flex items-center justify-center text-white font-bold hover:opacity-90 transition-opacity"
                   style="width: 36px; height: 36px; border-radius: 50%; background-color: #1A1A1A; font-size: 14px;">{{ $profileInitial }}</a>
            @else
                <a href="{{ route('login', ['next' => request()->getRequestUri()]) }}"
                   class="calm-press inline-flex items-center text-[13px] font-bold text-white bg-[#1A1A1A] hover:bg-black transition-colors {{ $fa }}"
                   style="padding: 10px 18px; border-radius: 999px; gap: 6px;">
                    {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
                </a>
            @endif
        </div>
    </div>

    {{-- Mobile: the bar on its own row --}}
    <div class="md:hidden" style="padding: 0 16px 12px;">
        @if($searchPill)
            <div x-data="calmTopSearch()" x-on:calm-search-state.window="set($event.detail)"
                 class="flex items-center bg-white w-full"
                 style="border-radius: 999px; height: 58px; padding: 0 7px; {{ $barShadow }}">
                <button type="button" @click="$dispatch('calm-open-search')"
                        class="flex-1 min-w-0 flex items-center justify-center {{ $fa }}" style="gap: 6px; height: 46px; border-radius: 999px;">
                    <span class="text-[13px] font-bold text-[#1A1A1A] truncate" x-text="city || @js($anyCity)"></span>
                </button>
                <span class="shrink-0" style="{{ $sepStyle }}"></span>
                <button type="button" @click="$dispatch('calm-open-search', { step: 'when' })"
                        class="flex-1 min-w-0 text-center text-[13px] font-semibold text-[#6B7280] truncate {{ $fa }}"
                        style="height: 46px; border-radius: 999px;" x-text="when || @js($anyTime)"></button>
                <span class="shrink-0" style="{{ $sepStyle }}"></span>
                <button type="button" @click="$dispatch('calm-open-search', { step: 'type' })"
                        class="flex-1 min-w-0 text-center text-[13px] font-semibold text-[#6B7280] truncate {{ $fa }}"
                        style="height: 46px; border-radius: 999px;" x-text="type || @js($anyType)"></button>
                <span class="shrink-0" style="{{ $sepStyle }}"></span>
                <button type="button" @click="$dispatch('calm-open-search', { step: 'area' })"
                        class="flex-1 min-w-0 text-center text-[13px] font-semibold text-[#6B7280] truncate {{ $fa }}"
                        style="height: 46px; border-radius: 999px;" x-text="area || @js($anyArea)"></button>
                <button type="button" @click="$dispatch('calm-open-search')" aria-label="{{ $isRtl ? 'بحث' : 'Search' }}"
                        class="shrink-0 flex items-center justify-center text-white bg-[#F88379]"
                        style="width: 44px; height: 44px; border-radius: 50%; margin-inline-start: 4px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                </button>
            </div>
        @else
            <a href="{{ route('landing') }}"
               class="flex items-center w-full bg-white"
               style="border-radius: 999px; height: 58px; padding: 0 7px 0 18px; gap: 10px; {{ $barShadow }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"></path>
                </svg>
                <span class="flex-1 text-[13px] font-bold text-[#1A1A1A] {{ $fa }}">{{ $isRtl ? 'ابحث في كالم' : 'Search Calm' }}</span>
                <span class="shrink-0 flex items-center justify-center text-white bg-[#F88379]" style="width: 44px; height: 44px; border-radius: 50%;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                </span>
            </a>
        @endif
    </div>
</header>
@if($searchPill)
    <script>
        if (!window.calmTopSearch) {
            window.calmTopSearch = function () {
                return {
                    city: null, when: null, type: null, area: null,
                    set(d) {
                        this.city = (d && d.cityName) || null;
                        this.when = (d && d.whenText) || null;
                        this.type = (d && d.typeName) || null;
                        this.area = (d && d.areaName) || null;
                    },
                };
            };
        }
    </script>
@endif
