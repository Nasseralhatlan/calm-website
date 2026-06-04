@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

<div class="bg-white text-center {{ $fa }}"
     style="padding: 64px 32px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
    <div style="font-size: 48px; line-height: 1; margin-bottom: 16px;">{{ $icon ?? '✨' }}</div>
    <h2 class="text-[18px] font-bold text-[#222]" style="margin-bottom: 8px;">{{ $title ?? '' }}</h2>
    <p class="text-[14px] text-[#717171]" style="margin-bottom: 0;">{{ $subtitle ?? ($isRtl ? 'سنوصلك بهذه الميزة قريباً.' : 'Coming soon.') }}</p>
</div>
