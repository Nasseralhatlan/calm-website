{{-- Desktop site header (≥1024px, the "web view"): logo left · centred search
     · language toggle + sign-in / profile dropdown right. Shared by the home,
     search-results and place pages so the whole guest web wears one chrome.

     $searchOpensModal = true  → the search bar dispatches `calm-open-search`
                                 (the page must include _web_search_modal)
                       = false → it links to the home page instead (default). --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $searchOpensModal = $searchOpensModal ?? false;

    $hdrMe = auth('api')->user();
    $hdrIsHost = $hdrMe?->isHost() ?? false;
    $hdrIsAdmin = $hdrMe?->isAdmin() ?? false;
    $hdrInitial = strtoupper(mb_substr($hdrMe?->name ?: ($hdrMe?->phone ?? '?'), 0, 1));
    $hdrLocaleUrl = url('/locale/'.($locale === 'ar' ? 'en' : 'ar'));

    // Shared style for the search control, whether it renders as a button or a
    // link. Absolutely centred on the row — the side groups have different
    // widths (and the right one changes between «تسجيل الدخول» and the avatar),
    // so a flex-centred bar would sit off-centre.
    $hdrSearchStyle = 'position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: min(440px, calc(100% - 580px)); height: 52px; border-radius: 999px; border: 1px solid #F1F1F1; box-shadow: 0 0 50px rgba(0,0,0,0.05); gap: 9px;';
    $hdrSearchClass = 'calm-press calm-round flex items-center justify-center bg-white';
@endphp
<header class="calm-show-desktop sticky top-0 z-40"
        style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 0 50px rgba(0,0,0,0.05);">
    {{-- LTR row so the logo sits visually LEFT and the buttons RIGHT even in
         Arabic (the Calm wordmark is Latin — kept on the left by request). --}}
    <div class="mx-auto relative flex items-center justify-between" dir="ltr" style="max-width: 1240px; padding: 16px 24px; gap: 20px;">
        <a href="{{ route('landing') }}" class="shrink-0 flex items-center">
            <img src="/assets/logo/logo.png" alt="Calm" style="height: 38px; width: auto;" draggable="false">
        </a>

        @php
            $hdrSearchInner = '<span dir="ltr" class="flex items-center" style="gap: 9px;">'
                .'<svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">'
                .'<path d="M13.125 13.125L11.25 11.25" stroke="black" stroke-width="1.5" stroke-linecap="round"/>'
                .'<ellipse cx="6.875" cy="7.49997" rx="5.625" ry="5.62497" stroke="black" stroke-width="1.5"/>'
                .'</svg>'
                .'<span class="text-[15px] font-semibold text-black '.$fa.'">'
                .($isRtl ? 'ابدء البحث' : 'Start searching').'</span></span>';
        @endphp
        @if($searchOpensModal)
            <button type="button" x-data @click="$dispatch('calm-open-search')"
                    class="{{ $hdrSearchClass }}" style="{{ $hdrSearchStyle }}">{!! $hdrSearchInner !!}</button>
        @else
            <a href="{{ route('landing') }}"
               class="{{ $hdrSearchClass }}" style="{{ $hdrSearchStyle }}">{!! $hdrSearchInner !!}</a>
        @endif

        {{-- Right: language toggle + sign-in / profile dropdown --}}
        <div class="shrink-0 flex items-center" style="gap: 6px;">
            <form method="POST" action="{{ $hdrLocaleUrl }}" class="m-0">
                @csrf
                <button type="submit" style="padding: 9px 12px; border-radius: 999px;"
                        class="calm-press text-[14px] font-semibold text-black hover:bg-[#F5F5F5] transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                    {{ $locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </form>

            @if($hdrMe)
                <div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open"
                            class="calm-press flex items-center bg-white hover:shadow-md transition-shadow"
                            style="gap: 9px; padding: 6px 6px; padding-inline-start: 14px; border: 1px solid #EBEBEB; border-radius: 999px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2" stroke-linecap="round">
                            <line x1="3" y1="7" x2="21" y2="7"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="17" x2="21" y2="17"></line>
                        </svg>
                        <span class="flex items-center justify-center text-white font-bold" style="width: 34px; height: 34px; border-radius: 50%; background-color: #000; font-size: 13px;">{{ $hdrInitial }}</span>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute bg-white {{ $isRtl ? 'text-right' : 'text-left' }}"
                         style="top: calc(100% + 10px); right: 0; width: 256px; border-radius: 20px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 10px 44px rgba(0,0,0,0.16); padding: 8px; z-index: 50;">
                        <div style="padding: 10px 12px 12px;">
                            <div class="font-bold text-black truncate {{ $fa }}" style="font-size: 15px;">{{ $hdrMe->name ?: ($isRtl ? 'مرحباً' : 'Welcome') }}</div>
                            @if($hdrMe->phone)
                                <div dir="ltr" class="{{ $isRtl ? 'text-right' : 'text-left' }} tabular-nums" style="font-size: 12px; color: #AAAAAA; margin-top: 3px;">0{{ $hdrMe->phone }}</div>
                            @endif
                        </div>
                        <div style="border-top: 1px solid #F1F1F1;"></div>
                        @php
                            $hdrItems = [
                                [route('user.trips'), $isRtl ? 'الحجوزات' : 'Reservations', '<rect x="3" y="5" width="18" height="16" rx="3"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="8" y1="2.5" x2="8" y2="6.5"></line><line x1="16" y1="2.5" x2="16" y2="6.5"></line>'],
                                [route('user.favorites'), $isRtl ? 'المفضلة' : 'Wishlist', '<path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>'],
                                [route('user.account'), $isRtl ? 'الملف الشخصي' : 'Profile', '<circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"></path>'],
                            ];
                        @endphp
                        <div style="padding: 6px 0;">
                            @foreach($hdrItems as [$hdrHref, $hdrLabel, $hdrIcon])
                                <a href="{{ $hdrHref }}" class="calm-press flex items-center hover:bg-[#F7F7F7]"
                                   style="gap: 12px; padding: 11px 12px; border-radius: 12px;">
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $hdrIcon !!}</svg>
                                    <span class="font-semibold text-black {{ $fa }}" style="font-size: 14px;">{{ $hdrLabel }}</span>
                                </a>
                            @endforeach
                            @if($hdrIsHost)
                                <a href="{{ route('user.places') }}" class="calm-press flex items-center hover:bg-[#F7F7F7]" style="gap: 12px; padding: 11px 12px; border-radius: 12px;">
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12"></path><rect x="9" y="13" width="6" height="8"></rect></svg>
                                    <span class="font-semibold text-black {{ $fa }}" style="font-size: 14px;">{{ $isRtl ? 'وضع المضيف' : 'Host mode' }}</span>
                                </a>
                            @endif
                            @if($hdrIsAdmin)
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
                <button type="button" x-data @click="$dispatch('calm-open-login')"
                        class="calm-press inline-flex items-center text-[14px] font-bold text-white hover:opacity-90 transition-opacity {{ $fa }}"
                        style="padding: 12px 24px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; background-color: #000;">
                    {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
                </button>
            @endif
        </div>
    </div>
</header>
