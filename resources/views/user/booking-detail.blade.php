@extends('layouts.user')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $audience = $isHost ? 'host' : 'guest';

    $place = $booking->place;
    $backRoute = $isHost ? 'user.bookings' : 'user.my-bookings';

    $supportPhone = config('support.phone');
    $supportWhatsapp = config('support.whatsapp');
    $supportEmail = config('support.email');
    $supportHours = config('support.hours');
    $waNumber = preg_replace('/[^0-9]/', '', (string) $supportWhatsapp);
@endphp

@section('title', $isRtl ? 'تفاصيل الحجز' : 'Booking details')
@section('heading', $place?->title ?: ($isRtl ? 'تفاصيل الحجز' : 'Booking details'))

@section('header-action')
    <a href="{{ route($backRoute) }}"
       class="inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7] border border-[#ebebeb] {{ $fa }}"
       style="padding: 10px 18px; gap: 8px; border-radius: 14px; font-size: 14px;">
        <span>{{ $isRtl ? '→' : '←' }}</span>
        <span>{{ $isHost ? ($isRtl ? 'حجوزات أماكني' : 'Bookings') : ($isRtl ? 'حجوزاتي' : 'My bookings') }}</span>
    </a>
@endsection

@section('main')
    <div style="max-width: 860px; display: flex; flex-direction: column; gap: 16px;">
        @include('partials._booking_detail', ['booking' => $booking, 'audience' => $audience])

        {{-- ── Support (in place of admin actions) ── --}}
        <div style="background:#fff;border-radius:24px;padding:24px;box-shadow:0px 8px 24px 0px rgba(0,0,0,0.05);">
            <h2 class="text-[15px] font-bold text-[#222] {{ $fa }}" style="margin-bottom: 4px;">{{ $isRtl ? 'تحتاج مساعدة؟' : 'Need help?' }}</h2>
            <p class="text-[13px] text-[#999] {{ $fa }}" style="margin-bottom: 16px;">
                {{ $isRtl ? 'لأي تعديل أو إلغاء أو استفسار عن هذا الحجز، تواصل مع فريق الدعم.' : 'For any change, cancellation or question about this booking, reach our support team.' }}
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 10px;">
                <a href="tel:{{ $supportPhone }}"
                   class="inline-flex items-center justify-center font-semibold text-[#222] bg-[#f7f7f7] hover:bg-[#efefef] transition-colors {{ $fa }}"
                   style="padding: 12px; border-radius: 14px; font-size: 14px; gap: 8px;">
                    <span>📞</span><span dir="ltr">{{ $supportPhone }}</span>
                </a>
                <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center font-semibold text-white transition-colors {{ $fa }}"
                   style="padding: 12px; border-radius: 14px; font-size: 14px; gap: 8px; background-color: #25D366;">
                    <span>💬</span><span>{{ $isRtl ? 'واتساب' : 'WhatsApp' }}</span>
                </a>
            </div>
            <div class="flex items-center flex-wrap text-[12px] text-[#999] {{ $fa }}" style="gap: 6px 16px; margin-top: 14px;">
                @if($supportEmail)<a href="mailto:{{ $supportEmail }}" class="hover:text-[#F88379]" dir="ltr">{{ $supportEmail }}</a>@endif
                @if($supportHours)<span>· {{ $isRtl ? 'ساعات العمل' : 'Hours' }}: <span dir="ltr">{{ $supportHours }}</span></span>@endif
            </div>
        </div>
    </div>
@endsection
