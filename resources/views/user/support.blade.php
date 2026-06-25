@extends('layouts.user')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $waNumber = $supportPhone ? preg_replace('/[^0-9]/', '', $supportPhone) : null;
@endphp

@section('title', $isRtl ? 'الدعم والمساعدة' : 'Support & help')
@section('heading', $isRtl ? 'الدعم والمساعدة' : 'Support & help')

@section('main')
    <div style="max-width: 760px; display: flex; flex-direction: column; gap: 16px;">
        <div class="bg-white" style="padding: 24px; border-radius: 24px; box-shadow: 0px 8px 24px 0px rgba(0,0,0,0.05);">
            <h2 class="text-[16px] font-bold text-[#222] {{ $fa }}" style="margin-bottom: 8px;">{{ $isRtl ? 'نحن هنا من أجلك' : "We're here for you" }}</h2>
            <p class="text-[14px] text-[#717171] {{ $fa }}" style="margin-bottom: 20px;">
                {{ $isRtl
                    ? 'فريق الدعم في كالم جاهز للرد على استفساراتك ومساعدتك في أي خطوة — من البحث والحجز وحتى الدفع — وحل أي مشكلة بأسرع وقت. تواصل معنا عبر القنوات الرسمية أدناه.'
                    : 'The Calm support team is ready to answer your questions and help at every step — from search and booking to payment. Reach us through the official channels below.' }}
            </p>

            @if($supportPhone || $supportEmail)
                <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 10px;">
                    @if($supportPhone)
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
                    @endif
                    @if($supportEmail)
                        <a href="mailto:{{ $supportEmail }}"
                           class="inline-flex items-center justify-center font-semibold text-[#222] bg-[#f7f7f7] hover:bg-[#efefef] transition-colors {{ $fa }} {{ $supportPhone ? 'sm:col-span-2' : '' }}"
                           style="padding: 12px; border-radius: 14px; font-size: 14px; gap: 8px;">
                            <span>✉️</span><span dir="ltr">{{ $supportEmail }}</span>
                        </a>
                    @endif
                </div>
            @else
                <p class="text-[14px] text-[#717171] {{ $fa }}">{{ $isRtl ? 'سيتم تحديث بيانات التواصل قريباً.' : 'Contact details will be available soon.' }}</p>
            @endif

            <p class="text-[13px] text-[#999] {{ $fa }}" style="margin-top: 18px;">
                {{ $isRtl ? 'نسعى للرد على جميع الرسائل في أقرب وقت خلال ساعات العمل. شكراً لاختيارك كالم.' : 'We aim to reply to every message as soon as possible during working hours. Thank you for choosing Calm.' }}
            </p>
        </div>
    </div>
@endsection
