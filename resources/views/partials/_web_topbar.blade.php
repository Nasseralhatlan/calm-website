{{-- Guest web top bar — Airbnb-style: logo · compact segmented search pill
     (city | when | type + coral circle button) · host CTA + locale +
     sign-in/avatar. Header size matches the place-page photo-tour header
     (h-20, white 0.7 + blur).

     With $searchPill = true the pill opens the reusable wizard
     (partials/_web_search_modal must be on the page); its segment labels stay
     live via the modal's 'calm-search-state' broadcasts. Otherwise the pill
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

    $hostCta = null;
    if ($me === null) {
        $hostCta = [route('login', ['next' => '/host-register']), $isRtl ? 'كن مضيفاً' : 'Become a host'];
    } elseif ($me->isHost()) {
        $hostCta = [route('user.places'), $isRtl ? 'أماكني' : 'My places'];
    } elseif (! $me->isAdmin()) {
        $hostCta = [route('host.places.create'), $isRtl ? 'كن مضيفاً' : 'Become a host'];
    }
@endphp
<header class="sticky top-0 z-40 border-b border-[#ebebeb]"
        style="background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
    <div class="mx-auto flex items-center justify-between h-20" style="max-width: 1200px; padding: 0 16px; gap: 12px;">
        <a href="{{ route('landing') }}" class="shrink-0 flex items-center">
            <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
        </a>

        {{-- Compact segmented pill (desktop; mobile gets its own row below) --}}
        <div class="hidden md:flex flex-1 justify-center min-w-0">
            @if($searchPill)
                <div x-data="calmTopSearch()" x-on:calm-search-state.window="set($event.detail)"
                     class="flex items-center bg-white border border-[#E5E7EB]"
                     style="border-radius: 999px; height: 54px; padding: 0 7px 0 0; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <button type="button" @click="$dispatch('calm-open-search')"
                            class="calm-press flex items-center hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                            style="gap: 8px; padding: 0 20px; height: 100%; border-radius: 999px;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"></path>
                        </svg>
                        <span class="text-[14px] font-bold text-[#1A1A1A] whitespace-nowrap" x-text="city || @js($anyCity)"></span>
                    </button>
                    <span class="shrink-0" style="width: 1px; height: 24px; background: #E5E7EB;"></span>
                    <button type="button" @click="$dispatch('calm-open-search', { step: 'when' })"
                            class="calm-press hover:bg-[#f7f7f7] transition-colors text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}"
                            style="padding: 0 20px; height: 100%;" x-text="when || @js($anyTime)"></button>
                    <span class="shrink-0" style="width: 1px; height: 24px; background: #E5E7EB;"></span>
                    <button type="button" @click="$dispatch('calm-open-search', { step: 'type' })"
                            class="calm-press hover:bg-[#f7f7f7] transition-colors text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}"
                            style="padding: 0 16px 0 20px; height: 100%;" x-text="type || @js($anyType)"></button>
                    <button type="button" @click="$dispatch('calm-open-search')" aria-label="{{ $isRtl ? 'بحث' : 'Search' }}"
                            class="calm-press shrink-0 flex items-center justify-center text-white bg-[#F88379] hover:bg-[#E66E64] transition-colors"
                            style="width: 40px; height: 40px; border-radius: 50%;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                        </svg>
                    </button>
                </div>
            @else
                <a href="{{ route('landing') }}"
                   class="calm-press flex items-center bg-white border border-[#E5E7EB]"
                   style="border-radius: 999px; height: 54px; padding: 0 7px 0 0; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <span class="flex items-center text-[14px] font-bold text-[#1A1A1A] whitespace-nowrap {{ $fa }}" style="gap: 8px; padding: 0 20px;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"></path>
                        </svg>
                        {{ $anyCity }}
                    </span>
                    <span class="shrink-0" style="width: 1px; height: 24px; background: #E5E7EB;"></span>
                    <span class="text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}" style="padding: 0 20px;">{{ $anyTime }}</span>
                    <span class="shrink-0" style="width: 1px; height: 24px; background: #E5E7EB;"></span>
                    <span class="text-[14px] font-semibold text-[#6B7280] whitespace-nowrap {{ $fa }}" style="padding: 0 16px 0 20px;">{{ $anyType }}</span>
                    <span class="shrink-0 flex items-center justify-center text-white bg-[#F88379]" style="width: 40px; height: 40px; border-radius: 50%;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
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

    {{-- Mobile: the pill on its own row --}}
    <div class="md:hidden" style="padding: 0 16px 12px;">
        @if($searchPill)
            <div x-data="calmTopSearch()" x-on:calm-search-state.window="set($event.detail)"
                 class="flex items-center bg-white border border-[#E5E7EB] w-full"
                 style="border-radius: 999px; height: 52px; padding: 0 6px 0 0; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <button type="button" @click="$dispatch('calm-open-search')"
                        class="flex-1 min-w-0 flex items-center justify-center {{ $fa }}" style="gap: 6px; height: 100%;">
                    <span class="text-[13px] font-bold text-[#1A1A1A] truncate" x-text="city || @js($anyCity)"></span>
                </button>
                <span class="shrink-0" style="width: 1px; height: 22px; background: #E5E7EB;"></span>
                <button type="button" @click="$dispatch('calm-open-search', { step: 'when' })"
                        class="flex-1 min-w-0 text-center text-[13px] font-semibold text-[#6B7280] truncate {{ $fa }}"
                        style="height: 100%;" x-text="when || @js($anyTime)"></button>
                <span class="shrink-0" style="width: 1px; height: 22px; background: #E5E7EB;"></span>
                <button type="button" @click="$dispatch('calm-open-search', { step: 'type' })"
                        class="flex-1 min-w-0 text-center text-[13px] font-semibold text-[#6B7280] truncate {{ $fa }}"
                        style="height: 100%;" x-text="type || @js($anyType)"></button>
                <button type="button" @click="$dispatch('calm-open-search')" aria-label="{{ $isRtl ? 'بحث' : 'Search' }}"
                        class="shrink-0 flex items-center justify-center text-white bg-[#F88379]"
                        style="width: 40px; height: 40px; border-radius: 50%;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                </button>
            </div>
        @else
            <a href="{{ route('landing') }}"
               class="flex items-center w-full bg-white border border-[#E5E7EB]"
               style="border-radius: 999px; height: 52px; padding: 0 6px 0 16px; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"></path>
                </svg>
                <span class="flex-1 text-[13px] font-bold text-[#1A1A1A] {{ $fa }}">{{ $isRtl ? 'ابحث في كالم' : 'Search Calm' }}</span>
                <span class="shrink-0 flex items-center justify-center text-white bg-[#F88379]" style="width: 40px; height: 40px; border-radius: 50%;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
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
                    city: null, when: null, type: null,
                    set(d) {
                        this.city = (d && d.cityName) || null;
                        this.when = (d && d.whenText) || null;
                        this.type = (d && d.typeName) || null;
                    },
                };
            };
        }
    </script>
@endif
