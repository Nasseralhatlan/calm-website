<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="/favicon.png">
    <title>@yield('title') · Calm</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { corner-shape: squircle; -webkit-corner-shape: squircle; }
        .legal-content { color: #3a3a3a; line-height: 1.95; font-size: 15px; }
        .legal-content > p:first-child { color: #555; }
        .legal-content h2 { font-size: 18px; font-weight: 700; color: #222; margin: 30px 0 10px; }
        .legal-content h3 { font-size: 15px; font-weight: 700; color: #222; margin: 18px 0 6px; }
        .legal-content p { margin: 0 0 12px; }
        .legal-content ul { margin: 0 0 14px; padding-inline-start: 22px; list-style: disc; }
        .legal-content li { margin-bottom: 7px; }
    </style>
</head>
<body class="min-h-screen antialiased font-arabic" style="background-color: #F8F8F8;">

    <header class="w-full bg-white border-b border-[#ebebeb] sticky top-0 z-30 backdrop-blur" style="background-color: rgba(255,255,255,0.92);">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between" style="max-width: 1100px; margin: 0 auto;">
            <a href="{{ route('landing') }}" class="flex items-center">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto select-none" draggable="false">
            </a>
            <a href="{{ route('landing') }}" class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] font-arabic" style="padding: 10px 16px; border-radius: 14px;">
                العودة للموقع
            </a>
        </div>
    </header>

    <main class="px-6 sm:px-10" style="padding-top: 36px; padding-bottom: 64px;">
        <div style="max-width: 760px; margin: 0 auto;">
            <div class="bg-white" style="padding: 32px 28px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                <h1 class="font-bold text-[#222]" style="font-size: 26px; margin-bottom: 6px;">@yield('title')</h1>
                <p class="text-[#999]" style="font-size: 13px; margin-bottom: 24px;">آخر تحديث: يونيو 2026</p>

                <div class="legal-content">
                    @yield('content')
                </div>
            </div>

            {{-- Cross-links to the other policy pages --}}
            <nav class="flex flex-wrap" style="gap: 8px 14px; margin-top: 24px; justify-content: center;">
                @php
                    $links = [
                        'pages.about' => 'عن كالم',
                        'pages.terms' => 'الشروط والأحكام',
                        'pages.privacy' => 'سياسة الخصوصية',
                        'pages.cancellation' => 'سياسة الإلغاء والاسترداد',
                        'pages.community' => 'معايير المجتمع',
                    ];
                @endphp
                @foreach($links as $route => $label)
                    <a href="{{ route($route) }}"
                       class="text-[13px] font-semibold {{ request()->routeIs($route) ? 'text-[#F88379]' : 'text-[#717171] hover:text-[#222]' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <p class="text-center text-[#bbb] text-[12px]" style="margin-top: 18px;">© {{ date('Y') }} Calm. جميع الحقوق محفوظة.</p>
        </div>
    </main>
</body>
</html>
