{{-- Slim guest-web footer — static links only. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $footerLinks = [
        [route('pages.about'), $isRtl ? 'عن كالم' : 'About'],
        [route('pages.faq'), $isRtl ? 'الأسئلة الشائعة' : 'FAQs'],
        [route('pages.support'), $isRtl ? 'الدعم' : 'Support'],
        [route('pages.terms'), $isRtl ? 'الشروط والأحكام' : 'Terms'],
        [route('pages.privacy'), $isRtl ? 'سياسة الخصوصية' : 'Privacy'],
        [route('pages.cancellation'), $isRtl ? 'سياسة الإلغاء' : 'Cancellation'],
    ];
@endphp
<footer class="border-t border-[#ebebeb] bg-white" style="margin-top: 48px;">
    <div class="mx-auto flex flex-col sm:flex-row items-center justify-between" style="max-width: 1200px; padding: 22px 16px; gap: 12px;">
        <div class="flex items-center flex-wrap justify-center" style="gap: 4px 18px;">
            @foreach($footerLinks as [$href, $label])
                <a href="{{ $href }}" class="text-[12px] text-[#717171] hover:text-[#222] transition-colors {{ $fa }}">{{ $label }}</a>
            @endforeach
        </div>
        <p class="text-[12px] text-[#b0b0b0] {{ $fa }}">© {{ now()->year }} {{ $isRtl ? 'كالم' : 'Calm' }}</p>
    </div>
</footer>
