@extends('layouts.user')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'الدعم' : 'Support')
@section('heading', $isRtl ? 'مركز الدعم' : 'Support center')

@section('main')
    <div class="bg-white"
         style="padding: 32px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <p class="text-[15px] text-[#222]" style="margin-bottom: 14px;">
            {{ $isRtl ? 'بحاجة لمساعدة؟ تواصل معنا مباشرةً.' : 'Need a hand? Reach out anytime.' }}
        </p>
        <div class="flex flex-col" style="gap: 10px;">
            <a href="mailto:khaled@calmapp.co"
               class="inline-flex items-center font-semibold text-[#222] bg-[#fafafa] hover:bg-[#f0f0f0]"
               style="padding: 12px 16px; gap: 10px; border-radius: 14px; font-size: 14px;" dir="ltr">
                ✉️ khaled@calmapp.co
            </a>
            <a href="tel:+966582727970"
               class="inline-flex items-center font-semibold text-[#222] bg-[#fafafa] hover:bg-[#f0f0f0]"
               style="padding: 12px 16px; gap: 10px; border-radius: 14px; font-size: 14px;" dir="ltr">
                📞 +966 58 272 7970
            </a>
        </div>
    </div>
@endsection
