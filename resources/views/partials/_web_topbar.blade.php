{{-- Guest web top bar — same size/treatment as the place-page photo-tour
     header (h-20, white 0.7 + blur). One large search bar (label + icon,
     curved corners): with $searchPill = true it opens the reusable search
     modal ($dispatch('calm-open-search') — include
     partials/_web_search_modal on the page); otherwise it links back to the
     home search. Right side: locale toggle + (guest → sign-in button,
     signed-in → avatar to profile/dashboard). --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
    $searchPill = $searchPill ?? false;
    $profileRoute = $me?->isAdmin() ? route('admin.dashboard') : route('profile');
    $profileInitial = strtoupper(mb_substr($me?->name ?: ($me?->phone ?? '?'), 0, 1));
    $searchLabel = $isRtl ? 'ابحث عن مكانك' : 'Find your place';
@endphp
<header class="sticky top-0 z-40 border-b border-[#ebebeb]"
        style="background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
    <div class="mx-auto flex items-center justify-between h-20" style="max-width: 1200px; padding: 0 16px; gap: 12px;">
        <a href="{{ route('landing') }}" class="shrink-0 flex items-center">
            <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
        </a>

        {{-- One large search bar (desktop; mobile gets its own row below) --}}
        <div class="hidden md:flex flex-1 justify-center min-w-0">
            @if($searchPill)
                <button type="button" x-data @click="$dispatch('calm-open-search')"
                        class="calm-press flex items-center w-full bg-white border border-[#E5E7EB] hover:border-[#1A1A1A] transition-colors"
                        style="border-radius: 18px; height: 54px; max-width: 560px; padding: 0 18px; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <span class="shrink-0 flex items-center justify-center text-white bg-[#F88379]"
                          style="width: 34px; height: 34px; border-radius: 12px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                        </svg>
                    </span>
                    <span class="text-[14px] font-bold text-[#1A1A1A] {{ $fa }}">{{ $searchLabel }}</span>
                </button>
            @else
                <a href="{{ route('landing') }}"
                   class="calm-press flex items-center w-full bg-white border border-[#E5E7EB] hover:border-[#1A1A1A] transition-colors"
                   style="border-radius: 18px; height: 54px; max-width: 560px; padding: 0 18px; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <span class="shrink-0 flex items-center justify-center text-white bg-[#F88379]"
                          style="width: 34px; height: 34px; border-radius: 12px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                        </svg>
                    </span>
                    <span class="text-[14px] font-bold text-[#1A1A1A] {{ $fa }}">{{ $searchLabel }}</span>
                </a>
            @endif
        </div>

        <div class="flex items-center shrink-0" style="gap: 6px;">
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
                   style="padding: 10px 18px; border-radius: 18px; gap: 6px;">
                    {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
                </a>
            @endif
        </div>
    </div>

    {{-- Mobile: the large search bar on its own row --}}
    <div class="md:hidden" style="padding: 0 16px 12px;">
        @if($searchPill)
            <button type="button" x-data @click="$dispatch('calm-open-search')"
                    class="calm-press flex items-center w-full bg-white border border-[#E5E7EB]"
                    style="border-radius: 18px; height: 52px; padding: 0 16px; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <span class="shrink-0 flex items-center justify-center text-white bg-[#F88379]"
                      style="width: 32px; height: 32px; border-radius: 11px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                </span>
                <span class="text-[14px] font-bold text-[#1A1A1A] {{ $fa }}">{{ $searchLabel }}</span>
            </button>
        @else
            <a href="{{ route('landing') }}"
               class="calm-press flex items-center w-full bg-white border border-[#E5E7EB]"
               style="border-radius: 18px; height: 52px; padding: 0 16px; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <span class="shrink-0 flex items-center justify-center text-white bg-[#F88379]"
                      style="width: 32px; height: 32px; border-radius: 11px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                </span>
                <span class="text-[14px] font-bold text-[#1A1A1A] {{ $fa }}">{{ $searchLabel }}</span>
            </a>
        @endif
    </div>
</header>
