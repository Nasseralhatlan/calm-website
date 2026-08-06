{{-- Guest web footer — Figma reference: bg #FBFBFB, three link columns
     (الشركة / روابط مهمة / التسجيل) + store badges, then a #F1F1F1-separated
     bottom row: grey logo + version + copyright on one side, social icons at
     40% opacity (all accounts calm_app) on the other. Secondary #AAAAAA. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $footerCols = [
        [
            'title' => $isRtl ? 'الشركة' : 'Company',
            'links' => [
                [route('pages.about'), $isRtl ? 'عن كالم' : 'About Calm'],
                [route('pages.about'), $isRtl ? 'الفريق' : 'Team'],
                [route('pages.support'), $isRtl ? 'التوظيف' : 'Careers'],
                [route('pages.support'), $isRtl ? 'تواصل معنا' : 'Contact us'],
            ],
        ],
        [
            'title' => $isRtl ? 'روابط مهمة' : 'Important links',
            'links' => [
                [route('pages.faq'), $isRtl ? 'الأسئلة الشائعة' : 'FAQs'],
                [route('pages.terms'), $isRtl ? 'الشروط و الأحكام' : 'Terms & conditions'],
                [route('pages.privacy'), $isRtl ? 'سياسة الخصوصية' : 'Privacy policy'],
                [route('pages.cancellation'), $isRtl ? 'سياسة الإلغاء و الإسترجاع' : 'Cancellation & refund policy'],
            ],
        ],
        [
            'title' => $isRtl ? 'التسجيل' : 'Sign up',
            'links' => [
                [route('login'), $isRtl ? 'تسجيل الدخول' : 'Sign in'],
                [route('login', ['next' => '/host-register']), $isRtl ? 'كن مضيف' : 'Become a host'],
            ],
        ],
    ];

    // All accounts: calm_app. Icons render at 40% opacity per the reference.
    $socials = [
        ['Instagram', 'https://instagram.com/calm_app', 'M12 2.2c3.2 0 3.58 0 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.81s-.01 3.54-.07 4.81c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.64.07-4.85.07s-3.58 0-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92C2.2 15.54 2.2 15.17 2.2 12s0-3.54.08-4.81C2.43 3.96 3.94 2.42 7.2 2.27 8.42 2.2 8.8 2.2 12 2.2Zm0 2.16c-3.14 0-3.49.01-4.73.07-2.4.11-3.4 1.12-3.51 3.51-.06 1.24-.07 1.59-.07 4.06s.01 2.82.07 4.06c.11 2.39 1.1 3.4 3.51 3.51 1.24.06 1.59.07 4.73.07s3.5-.01 4.74-.07c2.4-.11 3.4-1.12 3.51-3.51.06-1.24.07-1.59.07-4.06s-.01-2.82-.07-4.06c-.11-2.39-1.11-3.4-3.51-3.51-1.24-.06-1.6-.07-4.74-.07Zm0 2.62a5.02 5.02 0 1 1 0 10.04 5.02 5.02 0 0 1 0-10.04Zm0 2.16a2.86 2.86 0 1 0 0 5.72 2.86 2.86 0 0 0 0-5.72Zm5.23-3.32a1.17 1.17 0 1 1 0 2.34 1.17 1.17 0 0 1 0-2.34Z'],
        ['LinkedIn', 'https://linkedin.com/company/calm_app', 'M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29ZM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12ZM7.12 20.45H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.55C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.72C24 .77 23.2 0 22.22 0Z'],
        ['TikTok', 'https://tiktok.com/@calm_app', 'M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07Z'],
        ['X', 'https://x.com/calm_app', 'M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93Zm-1.29 19.5h2.04L6.49 3.24H4.3L17.61 20.65Z'],
        ['Snapchat', 'https://snapchat.com/add/calm_app', 'M12.21.79c.99 0 4.34.28 5.93 3.82.53 1.2.4 3.22.3 4.85l-.01.06c-.01.18-.02.35-.03.51.08.05.2.09.4.09.3-.02.66-.12 1.03-.3.17-.09.35-.1.47-.1.18 0 .36.03.51.09.45.15.73.48.73.84.02.45-.39.84-1.21 1.17-.09.03-.21.07-.34.12-.45.13-1.14.36-1.33.81-.09.22-.06.52.12.87l.01.01c.06.14 1.53 3.48 4.79 4.02.26.04.44.27.42.51 0 .07-.01.15-.04.22-.24.57-1.27.99-3.15 1.27-.06.09-.12.38-.16.57-.03.18-.08.36-.14.55-.07.27-.27.41-.55.41h-.03c-.14 0-.31-.03-.54-.07-.36-.08-.76-.14-1.27-.14-.3 0-.6.02-.91.08-.6.1-1.13.46-1.73.88-.85.6-1.82 1.29-3.29 1.29-.06 0-.12-.01-.18-.01h-.15c-1.47 0-2.43-.68-3.28-1.29-.6-.42-1.11-.78-1.7-.88-.32-.05-.63-.08-.93-.08-.54 0-.96.09-1.27.15-.21.04-.4.07-.54.07-.38 0-.53-.22-.59-.42-.06-.19-.09-.39-.13-.57-.05-.18-.11-.49-.17-.57-1.92-.22-2.95-.64-3.19-1.22-.03-.07-.05-.15-.05-.23-.02-.24.16-.46.42-.51 3.26-.54 4.73-3.88 4.79-4.02l.02-.03c.18-.34.22-.64.12-.87-.2-.43-.88-.66-1.33-.81-.12-.03-.24-.07-.35-.12-1.1-.43-1.25-.93-1.2-1.27.09-.48.68-.8 1.17-.8.15 0 .27.03.38.08.42.19.79.3 1.1.3.24 0 .39-.06.47-.11l-.05-.57c-.1-1.62-.22-3.65.31-4.83C7.4 1.08 10.74.81 11.73.81l.42-.02h.06Z'],
        ['WhatsApp', 'https://wa.me/', 'M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.13.3-.35.44-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.5 0 1.47 1.07 2.89 1.22 3.09.15.2 2.1 3.21 5.1 4.5.71.31 1.27.49 1.7.63.72.23 1.37.2 1.88.12.57-.09 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35Zm-5.42 7.4h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.9-9.88a9.83 9.83 0 0 1 9.89 9.9c0 5.44-4.44 9.87-9.9 9.87Zm8.41-18.28A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.33.16 11.89c0 2.1.55 4.14 1.59 5.94L.06 24l6.33-1.66a11.88 11.88 0 0 0 5.66 1.44h.01c6.55 0 11.89-5.33 11.89-11.89 0-3.18-1.24-6.16-3.49-8.4Z'],
    ];
@endphp
<footer style="background-color: #FBFBFB; margin-top: 64px;">
    <div class="mx-auto" style="max-width: 1240px; padding: 56px 24px 0;">
        <div class="grid grid-cols-2 md:grid-cols-4" style="gap: 40px 24px;">
            @foreach($footerCols as $col)
                <div>
                    <h3 class="text-[15px] font-bold text-black {{ $fa }}" style="margin-bottom: 18px;">{{ $col['title'] }}</h3>
                    <ul class="space-y-3">
                        @foreach($col['links'] as [$href, $label])
                            <li><a href="{{ $href }}" class="text-[13px] hover:text-black transition-colors {{ $fa }}" style="color: #AAAAAA;">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            {{-- Store badges --}}
            <div class="flex flex-col items-start" style="gap: 10px;">
                <a href="#" aria-label="App Store"
                   class="calm-press inline-flex items-center text-white" dir="ltr"
                   style="background-color: #000; border-radius: 9px; padding: 8px 14px; gap: 9px; min-width: 148px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M16.36 12.94c.03 3.2 2.8 4.27 2.83 4.28-.02.08-.44 1.52-1.46 3-.88 1.28-1.79 2.55-3.23 2.58-1.41.03-1.87-.84-3.48-.84-1.62 0-2.12.81-3.46.87-1.39.05-2.44-1.38-3.33-2.65-1.81-2.62-3.2-7.4-1.34-10.63.92-1.6 2.57-2.62 4.36-2.64 1.36-.03 2.65.92 3.48.92.83 0 2.39-1.13 4.03-.97.69.03 2.62.28 3.86 2.09-.1.06-2.3 1.35-2.26 3.99ZM13.71 5.04c.74-.89 1.23-2.13 1.1-3.36-1.06.04-2.34.7-3.1 1.6-.68.79-1.28 2.06-1.12 3.27 1.18.09 2.39-.6 3.12-1.51Z"/>
                    </svg>
                    <span class="flex flex-col" style="line-height: 1.15;">
                        <span style="font-size: 8px;">Download on the</span>
                        <span class="font-bold" style="font-size: 14px;">App Store</span>
                    </span>
                </a>
                <a href="#" aria-label="Google Play"
                   class="calm-press inline-flex items-center text-white" dir="ltr"
                   style="background-color: #000; border-radius: 9px; padding: 8px 14px; gap: 9px; min-width: 148px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M1.57.51c-.25.27-.4.68-.4 1.21v20.56c0 .53.15.94.4 1.2l.07.07 11.52-11.52v-.26L1.64.44l-.07.07Zm15.43 15.3-3.84-3.84v-.26l3.84-3.84.09.05 4.55 2.58c1.3.74 1.3 1.94 0 2.68l-4.55 2.58-.09.05Zm-.55.55L12.6 12.5 1.57 23.53c.43.45 1.13.51 1.93.06l12.95-7.23ZM3.5.41c-.8-.45-1.5-.4-1.93.06L12.6 11.5 16.45 7.66 3.5.41Z"/>
                    </svg>
                    <span class="flex flex-col" style="line-height: 1.15;">
                        <span style="font-size: 8px;">ANDROID APP ON</span>
                        <span class="font-bold" style="font-size: 14px;">Google Play</span>
                    </span>
                </a>
            </div>
        </div>

        {{-- Bottom row --}}
        {{-- Reference puts logo + version + © visually LEFT and the socials
             RIGHT (in RTL the first DOM child lands right, hence this order). --}}
        <div class="flex items-center justify-between flex-wrap"
             style="border-top: 1px solid #F1F1F1; margin-top: 56px; padding: 24px 0 28px; gap: 14px;">
            <div class="flex items-center" style="gap: 18px;">
                @foreach($socials as [$name, $href, $path])
                    <a href="{{ $href }}" target="_blank" rel="noopener" aria-label="{{ $name }}"
                       class="transition-opacity" style="opacity: 0.4;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='0.4'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#000"><path d="{{ $path }}"/></svg>
                    </a>
                @endforeach
            </div>
            <div class="flex items-center" style="gap: 12px;">
                <img src="/assets/logo/logo.png" alt="Calm" style="height: 22px; width: auto; opacity: 0.35; filter: grayscale(1);" draggable="false">
                <span class="text-[12px]" style="color: #AAAAAA;">V1.1.0</span>
                <span class="text-[12px] {{ $fa }}" style="color: #AAAAAA;">© {{ now()->year }} {{ $isRtl ? 'جميع الحقوق محفوظة شركة كالم لتك' : 'All rights reserved, Calm LTC' }}</span>
            </div>
        </div>
    </div>
</footer>
