@php
    /**
     * Unified sidebar nav. Used by both layouts/admin.blade.php and layouts/user.blade.php
     * so a user with multiple roles (admin + host + guest) sees the same shape everywhere.
     *
     * Section visibility:
     *  - Admin section → only if user is an admin
     *  - Host section  → if user is admin OR has any places of their own (hosts)
     *  - Guest section → everyone authenticated
     *  - Account section (Profile + Support) → everyone
     */
    $u = auth('api')->user();
    $isAdmin = $u?->isAdmin() ?? false;
    $isHost = $u?->isHost() ?? false;
    $current = request()->route()?->getName();
    $isRtl = app()->getLocale() === 'ar';

    $sections = [];

    if ($isAdmin) {
        $sections[] = [
            ['route' => 'admin.dashboard',              'label_ar' => 'الرئيسية',          'label_en' => 'Dashboard',        'icon' => 'grid'],
            ['route' => 'admin.places.index',           'label_ar' => 'الأماكن',           'label_en' => 'Places',           'icon' => 'house'],
            ['route' => 'admin.place-types.index',      'label_ar' => 'أنواع الأماكن',     'label_en' => 'Place types',      'icon' => 'layers'],
            ['route' => 'admin.attribute-groups.index', 'label_ar' => 'مجموعات الخصائص',   'label_en' => 'Attribute groups', 'icon' => 'folder'],
            ['route' => 'admin.attributes.index',       'label_ar' => 'الخصائص',           'label_en' => 'Attributes',       'icon' => 'tag'],
            ['route' => 'admin.countries.index',        'label_ar' => 'الدول',             'label_en' => 'Countries',        'icon' => 'globe'],
            ['route' => 'admin.cities.index',           'label_ar' => 'المدن',             'label_en' => 'Cities',           'icon' => 'building'],
            ['route' => 'admin.city-areas.index',       'label_ar' => 'الأحياء',           'label_en' => 'City areas',       'icon' => 'map'],
            ['route' => 'admin.settings.index',         'label_ar' => 'الإعدادات',         'label_en' => 'Settings',         'icon' => 'gear'],
        ];
    }

    if ($isAdmin || $isHost) {
        $sections[] = [
            ['route' => 'user.places',     'label_ar' => 'أماكني',         'label_en' => 'My places',      'icon' => 'house'],
            ['route' => 'user.bookings',   'label_ar' => 'حجوزات الضيوف',  'label_en' => 'Guest bookings', 'icon' => 'calendar'],
            ['route' => 'user.financials', 'label_ar' => 'المالية',        'label_en' => 'Finances',       'icon' => 'wallet'],
        ];
    }

    // Guest section — always shown for authenticated users
    $sections[] = [
        ['route' => 'user.my-bookings', 'label_ar' => 'حجوزاتي', 'label_en' => 'My bookings', 'icon' => 'ticket'],
        ['route' => 'user.favorites',   'label_ar' => 'المفضلة', 'label_en' => 'Favorites',   'icon' => 'heart'],
    ];

    // Account section — Profile covers all roles, Support is shared
    $sections[] = [
        ['route' => 'profile',       'label_ar' => 'الملف الشخصي', 'label_en' => 'Profile', 'icon' => 'user'],
        ['route' => 'user.support',  'label_ar' => 'الدعم',         'label_en' => 'Support', 'icon' => 'help'],
    ];
@endphp

@foreach($sections as $sectionIdx => $items)
    @if($sectionIdx > 0)
        {{-- Light divider between sections --}}
        <div aria-hidden="true" style="height: 1px; background-color: #ebebeb; margin: 12px 14px;"></div>
    @endif

    @foreach($items as $item)
        @php
            $active = $current === $item['route'];
            $label = $isRtl ? $item['label_ar'] : $item['label_en'];
        @endphp
        <a href="{{ route($item['route']) }}"
           class="flex items-center text-[14px] transition-colors
                  {{ $active ? 'bg-[#F88379] text-white font-bold' : 'text-[#717171] hover:bg-[#f7f7f7] hover:text-[#222] font-medium' }}"
           style="padding: 11px 14px; gap: 14px; border-radius: 14px;">
            <span class="flex items-center justify-center shrink-0" style="width: 22px; height: 22px;">
                @switch($item['icon'])
                    @case('grid')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                        </svg>
                        @break
                    @case('house')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"></path>
                        </svg>
                        @break
                    @case('layers')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 22 8 12 14 2 8 12 2"></polygon><polyline points="2 14 12 20 22 14"></polyline>
                        </svg>
                        @break
                    @case('folder')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"></path>
                        </svg>
                        @break
                    @case('tag')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                        @break
                    @case('globe')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path>
                            <path d="M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"></path>
                        </svg>
                        @break
                    @case('building')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="9" width="7" height="12" rx="1"></rect>
                            <rect x="13" y="3" width="8" height="18" rx="1"></rect>
                            <line x1="16" y1="7" x2="18" y2="7"></line><line x1="16" y1="11" x2="18" y2="11"></line><line x1="16" y1="15" x2="18" y2="15"></line>
                        </svg>
                        @break
                    @case('map')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21 3 6"></polygon>
                            <line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line>
                        </svg>
                        @break
                    @case('gear')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1A2 2 0 1 1 4.3 17l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1A2 2 0 1 1 7 4.3l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1A2 2 0 1 1 19.7 7l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"></path>
                        </svg>
                        @break
                    @case('calendar')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="5" width="18" height="16" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line>
                            <line x1="8" y1="3" x2="8" y2="7"></line><line x1="16" y1="3" x2="16" y2="7"></line>
                        </svg>
                        @break
                    @case('wallet')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="6" width="18" height="14" rx="2"></rect><path d="M16 13h2"></path><path d="M3 10h18"></path>
                        </svg>
                        @break
                    @case('ticket')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 1 0 0 2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 1 0 0-2V9z"></path>
                            <line x1="13" y1="7" x2="13" y2="17"></line>
                        </svg>
                        @break
                    @case('heart')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        @break
                    @case('user')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a8 8 0 0 1 16 0v1"></path>
                        </svg>
                        @break
                    @case('help')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        @break
                @endswitch
            </span>
            <span class="flex-1">{{ $label }}</span>
        </a>
    @endforeach
@endforeach
