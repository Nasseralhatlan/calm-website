@extends('layouts.legal')

@php $isRtl = app()->getLocale() === 'ar'; @endphp

@section('title', $isRtl ? 'سياسة الإلغاء والاسترداد' : 'Cancellation & Refunds')

@section('content')
@if($isRtl)
    <p>
        توضّح هذه السياسة قواعد إلغاء الحجوزات واسترداد المبالغ المدفوعة على منصة كالم، وتهدف إلى تحقيق
        التوازن بين حقوق الضيوف والمضيفين.
    </p>

    <h2>إلغاء الضيف</h2>
    <p>
        يمكن للضيف إلغاء الحجز قبل تاريخ الوصول، ويُحدَّد المبلغ المسترد وفقاً للمدة المتبقية على بداية
        الإقامة وسياسة المكان المعلنة وقت الحجز. قد لا تكون بعض الرسوم قابلة للاسترداد كرسوم الخدمة.
    </p>

    <h2>إلغاء المضيف</h2>
    <p>
        في حال إلغاء المضيف لحجز مؤكد، يحصل الضيف على استرداد كامل للمبلغ المدفوع. وقد تتخذ كالم إجراءات
        بحق المضيف عند تكرار الإلغاء غير المبرّر بما في ذلك تعليق الحساب.
    </p>

    <h2>مواعيد الاسترداد</h2>
    <p>
        تتم معالجة المبالغ المستردة إلى وسيلة الدفع الأصلية خلال مدة تتراوح عادةً بين ٣ و١٤ يوم عمل،
        وقد تختلف المدة الفعلية لإيداع المبلغ بحسب البنك أو مزوّد خدمة الدفع.
    </p>

    <h2>الظروف الاستثنائية</h2>
    <p>
        في الحالات الطارئة والخارجة عن الإرادة مثل الكوارث الطبيعية أو القرارات الرسمية التي تمنع الإقامة،
        قد تطبّق كالم استرداداً استثنائياً بعد مراجعة كل حالة على حدة وتقديم ما يثبت الظرف الطارئ.
    </p>
@else
    <p>
        This policy explains the rules for cancelling bookings and refunding amounts paid on the Calm
        platform, and aims to balance the rights of guests and hosts.
    </p>

    <h2>Guest cancellation</h2>
    <p>
        A guest may cancel a booking before the check-in date. The refundable amount is determined by
        the time remaining before the start of the stay and the place's policy as published at the
        time of booking. Some fees, such as the service fee, may be non-refundable.
    </p>

    <h2>Host cancellation</h2>
    <p>
        If a host cancels a confirmed booking, the guest receives a full refund of the amount paid.
        Calm may take action against the host in cases of repeated, unjustified cancellations,
        including account suspension.
    </p>

    <h2>Refund timelines</h2>
    <p>
        Refunds are processed back to the original payment method, typically within 3 to 14 business
        days. The actual time for the amount to be deposited may vary depending on the bank or payment
        service provider.
    </p>

    <h2>Exceptional circumstances</h2>
    <p>
        In emergencies and circumstances beyond control — such as natural disasters or official
        decisions that prevent the stay — Calm may apply an exceptional refund after reviewing each
        case individually and upon submission of proof of the emergency.
    </p>
@endif
@endsection
