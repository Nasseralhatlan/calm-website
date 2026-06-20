@extends('layouts.legal')

@php $isRtl = app()->getLocale() === 'ar'; @endphp

@section('title', $isRtl ? 'الدعم والمساعدة' : 'Support & help')

@section('content')
@if($isRtl)
    <p>
        نحن هنا من أجلك. فريق الدعم في كالم جاهز للرد على استفساراتك ومساعدتك في أي خطوة — من البحث
        والحجز وحتى الدفع — وحل أي مشكلة قد تواجهك بأسرع وقت ممكن. راحتك وثقتك أولويتنا.
    </p>

    <h2>تواصل معنا</h2>
    <p>لا تتردد في التواصل معنا عبر القنوات الرسمية التالية، وسنكون سعداء بخدمتك:</p>
@else
    <p>
        We're here for you. The Calm support team is ready to answer your questions and help at
        every step — from search and booking to payment — and to resolve any issue as quickly as
        possible. Your comfort and trust come first.
    </p>

    <h2>Contact us</h2>
    <p>Reach out through the official channels below — we'll be glad to help:</p>
@endif

<div style="display: flex; flex-direction: column; gap: 12px; margin: 18px 0 8px;">
    @if($supportEmail)
        <a href="mailto:{{ $supportEmail }}" dir="ltr"
           style="display: inline-flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 16px; background-color: #fff; border: 1px solid #ebebeb; font-weight: 600; color: #222; text-decoration: none;">
            <span style="font-size: 20px;">✉️</span>
            <span>{{ $supportEmail }}</span>
        </a>
    @endif
    @if($supportPhone)
        <a href="tel:{{ $supportPhone }}" dir="ltr"
           style="display: inline-flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 16px; background-color: #fff; border: 1px solid #ebebeb; font-weight: 600; color: #222; text-decoration: none;">
            <span style="font-size: 20px;">📞</span>
            <span>{{ $supportPhone }}</span>
        </a>
    @endif
    @if(! $supportEmail && ! $supportPhone)
        <p style="color: #717171;">{{ $isRtl ? 'سيتم تحديث بيانات التواصل قريباً.' : 'Contact details will be available soon.' }}</p>
    @endif
</div>

@if($isRtl)
    <p style="color: #717171; font-size: 14px;">
        نسعى للرد على جميع الرسائل في أقرب وقت ممكن خلال ساعات العمل. شكراً لاختيارك كالم.
    </p>
@else
    <p style="color: #717171; font-size: 14px;">
        We aim to reply to every message as soon as possible during working hours. Thank you for
        choosing Calm.
    </p>
@endif
@endsection
