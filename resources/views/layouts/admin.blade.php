@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    // Sidebar nav itself lives in partials/_sidebar_nav.blade.php so the
    // admin + user layouts stay in sync.
    $user = auth('api')->user();
    $initial = strtoupper(mb_substr($user?->name ?: ($user?->phone ?: ($user?->email ?: '?')), 0, 1));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="/favicon.png">
    <title>@yield('title', 'Admin') · Calm</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { corner-shape: squircle; -webkit-corner-shape: squircle; }
    </style>
</head>
<body class="min-h-screen antialiased text-[#222] {{ $fa }}" style="background-color: #F8F8F8;">

    {{-- ── Header (landing-style — white bg, sticky so it stays visible while scrolling long tables / review pages) ── --}}
    <header class="w-full border-b border-[#ebebeb] sticky top-0 z-30 backdrop-blur"
            style="background-color: rgba(255,255,255,0.92);">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center" style="gap: 10px;">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto select-none" draggable="false">
                <span class="text-[10px] font-bold uppercase tracking-[0.14em] {{ $fa }}"
                      style="padding: 4px 8px; background-color: #fff1ef; color: #F88379; border-radius: 999px;">
                    {{ $isRtl ? 'إدارة' : 'Admin' }}
                </span>
            </a>

            <div class="flex items-center" style="gap: 4px;">
                <a href="{{ route('landing') }}"
                   style="border-radius: 14px;"
                   class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] px-4 py-3 transition-colors {{ $fa }}">
                    {{ $isRtl ? 'زيارة الموقع' : 'View site' }}
                </a>

                <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                    @csrf
                    <button type="submit"
                            style="border-radius: 14px;"
                            class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] px-4 py-3 transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                        {{ $locale === 'ar' ? 'English' : 'العربية' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit"
                            style="border-radius: 14px;"
                            class="text-sm font-semibold text-[#dc2626] hover:bg-[#fef2f2] px-4 py-3 transition-colors {{ $fa }}">
                        {{ $isRtl ? 'تسجيل الخروج' : 'Sign out' }}
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- ── Sidebar (unified: admin + host + guest + account sections, separated by dividers) ── --}}
    <aside class="fixed z-20 flex flex-col admin-sidebar"
           style="top: 112px; width: 220px; gap: 6px;">
        @include('partials._sidebar_nav')
    </aside>

    {{-- ── Main content (outer padding matches header, inner offset clears the sidebar) ── --}}
    <main class="px-6 sm:px-10 lg:px-20" style="padding-top: 32px; padding-bottom: 64px;">
        <div class="mx-auto admin-content" style="max-width: 1200px;">

            {{-- Page heading --}}
            @hasSection('heading')
                <div class="flex items-center justify-between" style="margin-bottom: 24px; gap: 16px;">
                    <h1 class="text-[24px] sm:text-[28px] font-bold text-[#222]">@yield('heading')</h1>
                    @hasSection('header-action')
                        <div>@yield('header-action')</div>
                    @endif
                </div>
            @endif

            {{-- Alerts --}}
            @if(session('status'))
                <div class="flex items-start text-[14px] text-[#15803d] {{ $fa }}"
                     style="margin-bottom: 16px; padding: 14px 16px; border-radius: 18px; background-color: #ecfdf5; border: 1px solid rgba(21,128,61,0.25); box-shadow: 0px 10px 30px 0px rgba(21,128,61,0.06); gap: 10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" style="margin-top: 1px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="9 12 11 14 15 10"></polyline>
                    </svg>
                    <span class="flex-1 font-medium">{{ session('status') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="flex items-start text-[14px] text-[#7a2018] {{ $fa }}"
                     style="margin-bottom: 16px; padding: 14px 16px; border-radius: 18px; background-color: #fef3f2; border: 1px solid rgba(122,32,24,0.25); box-shadow: 0px 10px 30px 0px rgba(122,32,24,0.06); gap: 10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" style="margin-top: 1px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="13"></line>
                        <line x1="12" y1="16.5" x2="12" y2="17"></line>
                    </svg>
                    <ul class="flex-1 font-medium" style="list-style: disc; padding-inline-start: 18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('main')
        </div>
    </main>

    {{-- Sidebar shifted left by its own pill-padding (14px) so the icons
         line up with the logo's leading edge in the header.
         Main content offset = sidebar width (220px) + ~20px gap. --}}
    <style>
        .admin-sidebar { inset-inline-start: 10px; }
        .admin-content { padding-inline-start: 240px; }
        @media (min-width: 640px) {
            .admin-sidebar { inset-inline-start: 26px; }
        }
        @media (min-width: 1024px) {
            .admin-sidebar { inset-inline-start: 66px; }
        }
    </style>
</body>
</html>
