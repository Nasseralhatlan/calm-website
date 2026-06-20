@extends('layouts.legal')

@php $isRtl = app()->getLocale() === 'ar'; @endphp

@section('title', $isRtl ? 'عن كالم' : 'About Calm')

@section('content')
@if($isRtl)
    <p>
        كالم منصة سعودية لحجز الشاليهات والاستراحات والأماكن المميزة بكل سهولة وأمان. نربط بين أصحاب
        الأماكن (المضيفين) والباحثين عن إقامة مميزة (الضيوف) في تجربة حجز سلسة من البحث وحتى الدفع.
    </p>

    <h2>رؤيتنا</h2>
    <p>
        أن نكون الخيار الأول لحجز أماكن الإقامة والترفيه في المملكة، عبر تجربة موثوقة تجمع بين سهولة
        الاستخدام وجودة الخدمة وأمان المعاملات.
    </p>

    <h2>ماذا نقدّم</h2>
    <ul>
        <li>تصفّح أماكن متنوعة مع صور وتفاصيل وأسعار واضحة.</li>
        <li>حجز فوري وتقويم محدّث يمنع الحجوزات المزدوجة.</li>
        <li>دفع إلكتروني آمن وتأكيد فوري للحجز.</li>
        <li>دعم للمضيفين لإدارة أماكنهم وحجوزاتهم بسهولة.</li>
    </ul>

    <h2>تواصل معنا</h2>
    <p>
        يسعدنا تواصلك معنا واستقبال ملاحظاتك واستفساراتك عبر قنوات الدعم الرسمية المتاحة داخل التطبيق
        والموقع.
    </p>
@else
    <p>
        Calm is a Saudi platform for booking chalets, resthouses, and standout stays — easily and
        securely. We connect place owners (hosts) with people looking for a special stay (guests)
        through a seamless booking experience, from search all the way to payment.
    </p>

    <h2>Our vision</h2>
    <p>
        To be the first choice for booking stays and leisure spaces in the Kingdom, through a
        trusted experience that combines ease of use, quality of service, and secure transactions.
    </p>

    <h2>What we offer</h2>
    <ul>
        <li>Browse a variety of places with clear photos, details, and prices.</li>
        <li>Instant booking and an up-to-date calendar that prevents double bookings.</li>
        <li>Secure online payment with instant booking confirmation.</li>
        <li>Support for hosts to manage their places and bookings with ease.</li>
    </ul>

    <h2>Contact us</h2>
    <p>
        We'd love to hear from you. Reach out with your feedback and questions through the official
        support channels available in the app and on the website.
    </p>
@endif
@endsection
