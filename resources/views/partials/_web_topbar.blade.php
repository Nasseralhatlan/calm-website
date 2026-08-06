{{-- Guest web top bar — Figma reference: white, 138px tall (desktop),
     box-shadow 0 0 50px rgba(0,0,0,0.05), no border. Center: one simple
     search bar (⌕ ابحث) that opens the reusable wizard when
     $searchPill = true (include partials/_web_search_modal on the page),
     otherwise links back to the home search. End side: «كن مضيف» · locale ·
     sign-in button / avatar. Style tokens: primary #000, secondary #AAAAAA,
     separators #F1F1F1. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
    $searchPill = $searchPill ?? false;
    $profileRoute = $me?->isAdmin() ? route('admin.dashboard') : route('profile');
    $profileInitial = strtoupper(mb_substr($me?->name ?: ($me?->phone ?? '?'), 0, 1));

    $hostCta = null;
    if ($me === null) {
        $hostCta = [route('login', ['next' => '/host-register']), $isRtl ? 'كن مضيف' : 'Become a host'];
    } elseif ($me->isHost()) {
        $hostCta = [route('user.places'), $isRtl ? 'أماكني' : 'My places'];
    } elseif (! $me->isAdmin()) {
        $hostCta = [route('host.places.create'), $isRtl ? 'كن مضيف' : 'Become a host'];
    }
@endphp
<header class="sticky top-0 z-40 bg-white" style="box-shadow: 0 0 50px rgba(0,0,0,0.05);">
    <div class="mx-auto flex items-center justify-between py-4 md:py-0 md:h-[138px]" style="max-width: 1240px; padding-inline: 24px; gap: 16px;">
        <a href="{{ route('landing') }}" class="shrink-0 flex items-center">
            <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
        </a>

        {{-- Search bar (desktop; mobile gets its own row below) --}}
        <div class="hidden md:flex flex-1 justify-center min-w-0">
            @if($searchPill)
                <button type="button" x-data @click="$dispatch('calm-open-search')"
                        class="calm-press flex items-center justify-center bg-white w-full"
                        style="max-width: 450px; height: 56px; border-radius: 999px; box-shadow: 0 0 50px rgba(0,0,0,0.05);">
                    <span dir="ltr" class="flex items-center" style="gap: 9px;">
                        <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13.125 13.125L11.25 11.25" stroke="black" stroke-width="1.5" stroke-linecap="round"/>
                            <ellipse cx="6.875" cy="7.49997" rx="5.625" ry="5.62497" stroke="black" stroke-width="1.5"/>
                        </svg>
                        <span class="text-[15px] font-semibold text-black {{ $fa }}">{{ $isRtl ? 'ابحث' : 'Search' }}</span>
                    </span>
                </button>
            @else
                <a href="{{ route('landing') }}"
                   class="calm-press flex items-center justify-center bg-white w-full"
                   style="max-width: 450px; height: 56px; border-radius: 999px; box-shadow: 0 0 50px rgba(0,0,0,0.05);">
                    <span dir="ltr" class="flex items-center" style="gap: 9px;">
                        <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13.125 13.125L11.25 11.25" stroke="black" stroke-width="1.5" stroke-linecap="round"/>
                            <ellipse cx="6.875" cy="7.49997" rx="5.625" ry="5.62497" stroke="black" stroke-width="1.5"/>
                        </svg>
                        <span class="text-[15px] font-semibold text-black {{ $fa }}">{{ $isRtl ? 'ابحث' : 'Search' }}</span>
                    </span>
                </a>
            @endif
        </div>

        <div class="flex items-center shrink-0" style="gap: 10px;">
            @if($hostCta)
                <a href="{{ $hostCta[0] }}"
                   class="hidden sm:inline-flex text-[14px] font-semibold text-black hover:opacity-70 transition-opacity {{ $fa }}"
                   style="padding: 8px 6px;">{{ $hostCta[1] }}</a>
            @endif
            <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                @csrf
                <button type="submit" style="padding: 8px 6px;"
                        class="text-[14px] font-semibold text-black hover:opacity-70 transition-opacity {{ $locale === 'en' ? 'font-arabic' : '' }}">
                    {{ $locale === 'ar' ? 'english' : 'العربية' }}
                </button>
            </form>

            @if($me)
                <a href="{{ $profileRoute }}" aria-label="{{ $isRtl ? 'حسابي' : 'Account' }}"
                   class="calm-press flex items-center justify-center text-white font-bold hover:opacity-90 transition-opacity"
                   style="width: 38px; height: 38px; border-radius: 50%; background-color: #000; font-size: 14px;">{{ $profileInitial }}</a>
            @else
                <a href="{{ route('login', ['next' => request()->getRequestUri()]) }}"
                   class="calm-press inline-flex items-center text-[14px] font-bold text-white hover:opacity-90 transition-opacity {{ $fa }}"
                   style="padding: 12px 22px; border-radius: 12px; background-color: #000;">
                    {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
                </a>
            @endif
        </div>
    </div>

    {{-- Mobile: the search bar on its own row --}}
    <div class="md:hidden" style="padding: 0 16px 14px;">
        @if($searchPill)
            <button type="button" x-data @click="$dispatch('calm-open-search')"
                    class="calm-press flex items-center justify-center bg-white w-full"
                    style="height: 52px; border-radius: 999px; box-shadow: 0 0 50px rgba(0,0,0,0.05);">
                <span dir="ltr" class="flex items-center" style="gap: 9px;">
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.125 13.125L11.25 11.25" stroke="black" stroke-width="1.5" stroke-linecap="round"/>
                        <ellipse cx="6.875" cy="7.49997" rx="5.625" ry="5.62497" stroke="black" stroke-width="1.5"/>
                    </svg>
                    <span class="text-[15px] font-semibold text-black {{ $fa }}">{{ $isRtl ? 'ابحث' : 'Search' }}</span>
                </span>
            </button>
        @else
            <a href="{{ route('landing') }}"
               class="calm-press flex items-center justify-center bg-white w-full"
               style="height: 52px; border-radius: 999px; box-shadow: 0 0 50px rgba(0,0,0,0.05);">
                <span dir="ltr" class="flex items-center" style="gap: 9px;">
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.125 13.125L11.25 11.25" stroke="black" stroke-width="1.5" stroke-linecap="round"/>
                        <ellipse cx="6.875" cy="7.49997" rx="5.625" ry="5.62497" stroke="black" stroke-width="1.5"/>
                    </svg>
                    <span class="text-[15px] font-semibold text-black {{ $fa }}">{{ $isRtl ? 'ابحث' : 'Search' }}</span>
                </span>
            </a>
        @endif
    </div>
</header>
