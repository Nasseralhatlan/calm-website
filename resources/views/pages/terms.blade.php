@extends('layouts.legal')

@php $isRtl = app()->getLocale() === 'ar'; @endphp

@section('title', $isRtl ? 'الشروط والأحكام' : 'Terms & Conditions')

@section('content')
@if($isRtl)
    <p>
        تحكم هذه الشروط والأحكام استخدامك لمنصة كالم وتطبيقاتها وخدماتها. باستخدامك للمنصة فإنك تقرّ
        بأنك قرأت هذه الشروط وفهمتها ووافقت على الالتزام بها. إذا كنت لا توافق على أي بند منها، يرجى
        التوقف عن استخدام المنصة.
    </p>

    <h2>قواعد المنصة</h2>
    <p>
        كالم منصة وسيطة تتيح للمضيفين عرض أماكنهم وتمكّن الضيوف من حجزها. لا تملك كالم الأماكن المعروضة
        ولا تديرها، ودورها يقتصر على تسهيل التواصل وإتمام الحجز والدفع بين الطرفين.
    </p>
    <ul>
        <li>يجب أن يكون المستخدم بالغاً سن الرشد ومؤهلاً قانونياً لإبرام العقود.</li>
        <li>يلتزم المستخدم بتقديم معلومات صحيحة ودقيقة وتحديثها عند الحاجة.</li>
        <li>يُمنع استخدام المنصة لأي غرض غير مشروع أو مخالف للأنظمة المعمول بها في المملكة العربية السعودية.</li>
    </ul>

    <h2>قواعد الحجز</h2>
    <p>
        يُعد الحجز مؤكداً بعد إتمام عملية الدفع بنجاح وصدور تأكيد من المنصة. تُعرض أوقات الوصول والمغادرة
        والأسعار والقواعد الخاصة بكل مكان قبل تأكيد الحجز، ويُعد إتمام الحجز موافقة عليها.
    </p>

    <h2>التزامات المضيف</h2>
    <ul>
        <li>تقديم وصف دقيق وصور حقيقية للمكان وتوفيره بالحالة المعلن عنها.</li>
        <li>احترام الحجوزات المؤكدة وعدم إلغائها دون سبب مبرر.</li>
        <li>تحديث التقويم والأسعار باستمرار لتجنّب الحجوزات المزدوجة.</li>
    </ul>

    <h2>التزامات الضيف</h2>
    <ul>
        <li>استخدام المكان بطريقة مسؤولة والمحافظة عليه واحترام قواعد المضيف.</li>
        <li>الالتزام بأوقات الوصول والمغادرة وعدد الضيوف المتفق عليه.</li>
        <li>تحمّل مسؤولية أي ضرر يلحق بالمكان نتيجة سوء الاستخدام.</li>
    </ul>

    <h2>المدفوعات</h2>
    <p>
        تتم جميع المدفوعات إلكترونياً عبر بوابة دفع آمنة. يشمل المبلغ الإجمالي قيمة الحجز وضريبة القيمة
        المضافة المطبّقة. تحتفظ كالم بعمولة الخدمة من مستحقات المضيف، ويتم تحويل صافي المستحقات للمضيف
        وفق السياسات المعتمدة.
    </p>

    <h2>حدود المسؤولية</h2>
    <p>
        تعمل كالم كوسيط فقط ولا تتحمل المسؤولية عن جودة الأماكن أو سلوك المستخدمين أو أي نزاع ينشأ بين
        المضيف والضيف. تُقدَّم الخدمة "كما هي" دون أي ضمانات صريحة أو ضمنية، وتقتصر مسؤولية كالم في جميع
        الأحوال على الحدود التي يسمح بها النظام.
    </p>

    <h2>تعليق الحساب</h2>
    <p>
        يحق لكالم تعليق أو إيقاف أي حساب يخالف هذه الشروط أو يسيء استخدام المنصة أو يشكّل خطراً على
        المستخدمين الآخرين، وذلك دون إشعار مسبق وبما يحفظ حقوق الأطراف الأخرى.
    </p>
@else
    <p>
        These Terms &amp; Conditions govern your use of the Calm platform, its apps, and its
        services. By using the platform, you acknowledge that you have read, understood, and agreed
        to be bound by these terms. If you do not agree to any of them, please stop using the platform.
    </p>

    <h2>Platform rules</h2>
    <p>
        Calm is an intermediary platform that lets hosts list their places and enables guests to book
        them. Calm does not own or manage the listed places; its role is limited to facilitating
        communication, booking, and payment between the two parties.
    </p>
    <ul>
        <li>The user must be of legal age and legally qualified to enter into contracts.</li>
        <li>The user agrees to provide accurate, correct information and to update it when needed.</li>
        <li>Using the platform for any unlawful purpose, or one that violates the regulations in force in the Kingdom of Saudi Arabia, is prohibited.</li>
    </ul>

    <h2>Booking rules</h2>
    <p>
        A booking is confirmed once payment is completed successfully and a confirmation is issued by
        the platform. Each place's check-in and check-out times, prices, and rules are shown before
        the booking is confirmed, and completing the booking constitutes acceptance of them.
    </p>

    <h2>Host obligations</h2>
    <ul>
        <li>Provide an accurate description and genuine photos of the place, and make it available in the advertised condition.</li>
        <li>Honor confirmed bookings and not cancel them without a justified reason.</li>
        <li>Keep the calendar and prices continuously updated to avoid double bookings.</li>
    </ul>

    <h2>Guest obligations</h2>
    <ul>
        <li>Use the place responsibly, keep it in good condition, and respect the host's rules.</li>
        <li>Adhere to the agreed check-in and check-out times and guest count.</li>
        <li>Take responsibility for any damage to the place resulting from misuse.</li>
    </ul>

    <h2>Payments</h2>
    <p>
        All payments are made electronically through a secure payment gateway. The total amount
        includes the booking value and the applicable value-added tax. Calm retains its service
        commission from the host's dues, and the net amount is transferred to the host in accordance
        with the approved policies.
    </p>

    <h2>Limitation of liability</h2>
    <p>
        Calm acts solely as an intermediary and is not responsible for the quality of the places, the
        conduct of users, or any dispute that arises between a host and a guest. The service is
        provided "as is" without any express or implied warranties, and Calm's liability in all cases
        is limited to the extent permitted by law.
    </p>

    <h2>Account suspension</h2>
    <p>
        Calm reserves the right to suspend or disable any account that violates these terms, misuses
        the platform, or poses a risk to other users — without prior notice and in a manner that
        preserves the rights of other parties.
    </p>
@endif
@endsection
