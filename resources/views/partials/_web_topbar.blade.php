{{-- Guest web top bar.
     $searchPill = true  → renders the 4-segment Airbnb-style pill wired to the
                           home page's Alpine component (openSearch()/labels).
     $searchPill = false → renders a compact pill that links back to the home
                           search. Right side: locale toggle + (guest → sign-in
                           button, signed-in → avatar to profile/dashboard). --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
    $searchPill = $searchPill ?? false;
    $profileRoute = $me?->isAdmin() ? route('admin.dashboard') : route('profile');
    $profileInitial = strtoupper(mb_substr($me?->name ?: ($me?->phone ?? '?'), 0, 1));

    $pillSegments = [
        ['key' => 'city', 'title' => $isRtl ? 'المدينة' : 'City',  'label' => 'cityLabel()'],
        ['key' => 'when', 'title' => $isRtl ? 'متى' : 'When',      'label' => 'whenLabel()'],
        ['key' => 'type', 'title' => $isRtl ? 'النوع' : 'Type',    'label' => 'typeLabel()'],
        ['key' => 'area', 'title' => $isRtl ? 'الحي' : 'Area',     'label' => 'areaLabel()'],
    ];
@endphp
<header class="sticky top-0 z-40 bg-white border-b border-[#ebebeb]">
    <div class="mx-auto flex items-center justify-between" style="max-width: 1200px; padding: 0 16px; height: 68px; gap: 12px;">
        <a href="{{ route('landing') }}" class="shrink-0 flex items-center">
            <img src="/assets/logo/logo.png" alt="Calm" style="height: 34px; width: auto;" draggable="false">
        </a>

        @if($searchPill)
            {{-- Desktop pill (mobile version renders on its own row below) --}}
            <div class="hidden md:flex flex-1 justify-center min-w-0">
                <div class="flex items-center bg-white border border-[#e5e5e5] w-full min-w-0"
                     style="border-radius: 999px; height: 54px; max-width: 620px; box-shadow: 0 6px 18px rgba(0,0,0,0.07); padding-inline-end: 6px;">
                    @foreach($pillSegments as $i => $seg)
                        @if($i > 0)
                            <span class="shrink-0" style="width: 1px; height: 26px; background: #ebebeb;"></span>
                        @endif
                        <button type="button" @click="openSearch('{{ $seg['key'] }}')"
                                class="flex-1 min-w-0 text-start hover:bg-[#f7f7f7] transition-colors"
                                style="padding: 8px 18px; border-radius: 999px;">
                            <span class="block text-[11px] font-bold text-[#222] {{ $fa }}">{{ $seg['title'] }}</span>
                            <span class="block text-[12px] text-[#717171] truncate {{ $fa }}" x-text="{{ $seg['label'] }}"></span>
                        </button>
                    @endforeach
                    <button type="button" @click="openSearch('city')" aria-label="{{ $isRtl ? 'بحث' : 'Search' }}"
                            class="shrink-0 flex items-center justify-center text-white bg-[#F88379] hover:bg-[#f56b60] transition-colors"
                            style="width: 40px; height: 40px; border-radius: 50%;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                        </svg>
                    </button>
                </div>
            </div>
        @else
            <a href="{{ route('landing') }}"
               class="hidden md:inline-flex items-center bg-white border border-[#e5e5e5] text-[13px] font-semibold text-[#717171] hover:text-[#222] transition-colors {{ $fa }}"
               style="border-radius: 999px; padding: 12px 22px; gap: 10px; box-shadow: 0 4px 14px rgba(0,0,0,0.06);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round">
                    <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                </svg>
                <span>{{ $isRtl ? 'ابحث في كالم' : 'Search Calm' }}</span>
            </a>
        @endif

        <div class="flex items-center shrink-0" style="gap: 6px;">
            <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                @csrf
                <button type="submit" style="border-radius: 999px; padding: 9px 14px;"
                        class="text-[13px] font-semibold text-[#222] hover:bg-[#f7f7f7] transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                    {{ $locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </form>

            @if($me)
                <a href="{{ $profileRoute }}" aria-label="{{ $isRtl ? 'حسابي' : 'Account' }}"
                   class="flex items-center justify-center text-white font-bold hover:opacity-90 transition-opacity"
                   style="width: 36px; height: 36px; border-radius: 50%; background-color: #222; font-size: 14px;">{{ $profileInitial }}</a>
            @else
                <a href="{{ route('login', ['next' => request()->getRequestUri()]) }}"
                   class="inline-flex items-center text-[13px] font-bold text-white bg-[#222] hover:bg-black transition-colors {{ $fa }}"
                   style="padding: 10px 18px; border-radius: 999px; gap: 6px;">
                    {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
                </a>
            @endif
        </div>
    </div>

    @if($searchPill)
        {{-- Mobile pill --}}
        <div class="md:hidden" style="padding: 0 16px 12px;">
            <div class="flex items-center bg-white border border-[#e5e5e5] w-full"
                 style="border-radius: 999px; height: 50px; box-shadow: 0 6px 18px rgba(0,0,0,0.07); padding-inline-end: 5px;">
                @foreach($pillSegments as $i => $seg)
                    @if($i > 0)
                        <span class="shrink-0" style="width: 1px; height: 22px; background: #ebebeb;"></span>
                    @endif
                    <button type="button" @click="openSearch('{{ $seg['key'] }}')" class="flex-1 min-w-0 text-center" style="padding: 5px 4px;">
                        <span class="block font-bold text-[#222] {{ $fa }}" style="font-size: 10px;">{{ $seg['title'] }}</span>
                        <span class="block text-[#717171] truncate {{ $fa }}" style="font-size: 11px;" x-text="{{ $seg['label'] }}"></span>
                    </button>
                @endforeach
                <button type="button" @click="openSearch('city')" aria-label="{{ $isRtl ? 'بحث' : 'Search' }}"
                        class="shrink-0 flex items-center justify-center text-white bg-[#F88379]"
                        style="width: 40px; height: 40px; border-radius: 50%;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                </button>
            </div>
        </div>
    @endif
</header>
