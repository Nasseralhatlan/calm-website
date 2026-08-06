{{-- Floating app-style tab bar — signed-in users only (guests get the top-bar
     sign-in button instead). Fixed bottom-center pill: search (home),
     bookings, favorites, account. Pages that include it should reserve
     ~110px of bottom padding so content never hides behind it. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();

    $navItems = [
        [
            'href' => route('landing'),
            'active' => request()->routeIs('landing'),
            'label' => $isRtl ? 'البحث' : 'Search',
            'icon' => '<circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>',
        ],
        [
            'href' => route('user.my-bookings'),
            'active' => request()->routeIs('user.my-bookings'),
            'label' => $isRtl ? 'حجوزاتي' : 'Bookings',
            'icon' => '<rect x="3" y="5" width="18" height="16" rx="3"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="8" y1="2.5" x2="8" y2="6.5"></line><line x1="16" y1="2.5" x2="16" y2="6.5"></line>',
        ],
        [
            'href' => route('user.favorites'),
            'active' => request()->routeIs('user.favorites'),
            'label' => $isRtl ? 'المفضلة' : 'Favorites',
            'icon' => '<path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>',
        ],
        [
            'href' => $me?->isAdmin() ? route('admin.dashboard') : route('profile'),
            'active' => request()->routeIs('profile') || request()->routeIs('admin.dashboard'),
            'label' => $isRtl ? 'حسابي' : 'Account',
            'icon' => '<circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"></path>',
        ],
    ];
@endphp
@if($me)
    {{-- Blur + white tint, coral active / #9CA3AF inactive, icon 22, label
         11/14 medium — mobile tab-bar treatment (spec §5.6) in the floating
         pill shape this site uses. --}}
    <nav aria-label="{{ $isRtl ? 'التنقل' : 'Navigation' }}" class="fixed z-40"
         style="bottom: 16px; left: 50%; transform: translateX(-50%);">
        <div class="flex items-center border border-[#F1F1F1]"
             style="border-radius: 999px; padding: 5px; gap: 2px; background-color: rgba(255,255,255,0.8); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 0 25px rgba(0,0,0,0.05), 0 12px 32px rgba(0,0,0,0.12);">
            @foreach($navItems as $item)
                <a href="{{ $item['href'] }}"
                   class="calm-press flex flex-col items-center justify-center transition-colors {{ $item['active'] ? 'text-[#F88379]' : 'text-[#9CA3AF] hover:text-[#6B7280]' }}"
                   style="width: 68px; padding: 8px 0 7px; border-radius: 999px; gap: 4px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                    <span class="font-medium {{ $fa }}" style="font-size: 11px; line-height: 14px;">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>
@endif
