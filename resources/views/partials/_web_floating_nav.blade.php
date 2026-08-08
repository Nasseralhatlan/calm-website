{{-- App tab bar — ALWAYS visible (guests included, like the mobile app):
     استكشف · المفضلة · الحجوزات · حسابى. Gated tabs render their own inline
     sign-in prompt. Blur + white tint, coral active / grey inactive.
     Pages that include it should reserve ~110px of bottom padding. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $navItems = [
        [
            'href' => route('landing'),
            'active' => request()->routeIs('landing'),
            'label' => $isRtl ? 'استكشف' : 'Explore',
            'icon' => '<circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>',
        ],
        [
            'href' => route('user.favorites'),
            'active' => request()->routeIs('user.favorites'),
            'label' => $isRtl ? 'الـمفضلة' : 'Wishlist',
            'icon' => '<path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>',
        ],
        [
            'href' => route('user.trips'),
            'active' => request()->routeIs('user.trips'),
            'label' => $isRtl ? 'الحجوزات' : 'Bookings',
            'icon' => '<rect x="3" y="5" width="18" height="16" rx="3"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="8" y1="2.5" x2="8" y2="6.5"></line><line x1="16" y1="2.5" x2="16" y2="6.5"></line>',
        ],
        [
            'href' => route('user.account'),
            'active' => request()->routeIs('user.account'),
            'label' => $isRtl ? 'حسابى' : 'Account',
            'icon' => '<circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"></path>',
        ],
    ];
@endphp
<nav aria-label="{{ $isRtl ? 'التنقل' : 'Navigation' }}" class="fixed inset-x-0 bottom-0 z-40"
     style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 0 25px rgba(0,0,0,0.05);">
    <div class="mx-auto flex items-stretch"
         style="max-width: 560px; padding: 8px 12px calc(8px + env(safe-area-inset-bottom, 0px));">
        @foreach($navItems as $item)
            <a href="{{ $item['href'] }}"
               class="calm-press flex-1 flex flex-col items-center justify-center transition-colors {{ $item['active'] ? 'text-[#F88379]' : 'text-[#9CA3AF] hover:text-[#6B7280]' }}"
               style="padding: 6px 0 4px; gap: 4px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                <span class="font-medium {{ $fa }}" style="font-size: 11px; line-height: 14px;">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
