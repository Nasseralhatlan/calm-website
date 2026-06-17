@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', $isRtl ? 'العودة إلى التطبيق' : 'Returning to the app')

@section('body')
    {{-- Dumb landing target. The mobile WebView matches this URL
         (calm-after-payment / calm-back-payment) and takes over; this page only
         shows for the brief moment before the app intercepts, or if opened in a
         plain browser. --}}
    <div class="min-h-screen flex items-center justify-center" style="background-color: #F8F8F8; padding: 24px;"
         data-payment-return="{{ $cancelled ? 'cancelled' : 'success' }}">
        <div class="bg-white text-center {{ $fa }}"
             style="max-width: 380px; width: 100%; padding: 36px 28px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.06);">
            <div style="font-size: 44px; line-height: 1; margin-bottom: 16px;">
                {{ $cancelled ? '↩️' : '✅' }}
            </div>

            <h1 class="font-bold text-[#222]" style="font-size: 20px; margin-bottom: 8px;">
                @if($cancelled)
                    {{ $isRtl ? 'تم إلغاء الدفع' : 'Payment cancelled' }}
                @else
                    {{ $isRtl ? 'تمت معالجة الدفع' : 'Payment processed' }}
                @endif
            </h1>

            <p class="text-[#717171]" style="font-size: 14px; margin-bottom: 20px;">
                {{ $isRtl ? 'جارٍ العودة إلى التطبيق…' : 'Returning to the app…' }}
            </p>

            <div class="inline-flex items-center justify-center" style="gap: 8px;">
                <svg class="calm-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="#F88379" stroke-width="3" stroke-linecap="round">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                    <path d="M22 12a10 10 0 0 1-10 10"/>
                </svg>
                <span class="text-[#999]" style="font-size: 12px;">
                    {{ $isRtl ? 'يمكنك إغلاق هذه النافذة' : 'You can close this window' }}
                </span>
            </div>
        </div>
    </div>
@endsection
