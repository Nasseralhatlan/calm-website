@extends('layouts.legal')

@php $isRtl = app()->getLocale() === 'ar'; @endphp

@section('title', $isRtl ? 'معايير المجتمع' : 'Community Standards')

@section('content')
@if($isRtl)
    <p>
        نسعى في كالم إلى بناء مجتمع آمن وموثوق يحترم فيه الجميع بعضهم البعض. توضّح هذه المعايير السلوكيات
        المتوقّعة من المستخدمين والممارسات المحظورة على المنصة.
    </p>

    <h2>السلوكيات المحظورة</h2>
    <p>
        يُمنع أي سلوك يضرّ بالمستخدمين أو بالمنصة، بما في ذلك انتحال الصفة، أو نشر محتوى مخالف للآداب
        العامة أو الأنظمة، أو محاولة التحايل على آلية الدفع والحجز داخل المنصة.
    </p>

    <h2>الاحتيال</h2>
    <p>
        يُمنع منعاً باتاً أي شكل من أشكال الاحتيال أو التضليل، بما في ذلك استخدام وسائل دفع غير مشروعة أو
        تقديم معلومات كاذبة بهدف الحصول على منفعة غير مستحقة.
    </p>

    <h2>القوائم الوهمية</h2>
    <p>
        يُحظر نشر أماكن غير حقيقية أو غير متاحة فعلياً للحجز، أو استخدام صور أو أوصاف مضلّلة لا تعبّر عن
        المكان الحقيقي.
    </p>

    <h2>التقييمات المزيفة</h2>
    <p>
        يجب أن تعكس التقييمات تجارب حقيقية. يُمنع نشر تقييمات مزيفة أو متبادلة أو التلاعب بنظام التقييم
        بأي وسيلة.
    </p>

    <h2>الإضرار بالممتلكات</h2>
    <p>
        يلتزم الضيوف بالمحافظة على الأماكن وممتلكاتها، ويتحمّل المتسبب مسؤولية أي ضرر متعمّد أو ناتج عن
        سوء الاستخدام.
    </p>

    <h2>التحرش</h2>
    <p>
        لا تتسامح كالم مطلقاً مع أي شكل من أشكال التحرش أو التهديد أو الكراهية أو التمييز. وتُتخذ إجراءات
        فورية بحق المخالفين قد تصل إلى إيقاف الحساب نهائياً وإبلاغ الجهات المختصة عند الاقتضاء.
    </p>
@else
    <p>
        At Calm, we strive to build a safe, trustworthy community where everyone respects one another.
        These standards outline the behavior expected of users and the practices prohibited on the
        platform.
    </p>

    <h2>Prohibited behavior</h2>
    <p>
        Any behavior that harms users or the platform is prohibited, including impersonation, posting
        content that violates public decency or regulations, or attempting to circumvent the platform's
        payment and booking mechanism.
    </p>

    <h2>Fraud</h2>
    <p>
        Any form of fraud or deception is strictly prohibited, including using unlawful payment methods
        or providing false information to obtain an undue benefit.
    </p>

    <h2>Fake listings</h2>
    <p>
        Publishing places that are not real or not actually available for booking, or using misleading
        photos or descriptions that do not represent the actual place, is prohibited.
    </p>

    <h2>Fake reviews</h2>
    <p>
        Reviews must reflect genuine experiences. Posting fake or reciprocal reviews, or manipulating
        the rating system by any means, is prohibited.
    </p>

    <h2>Property damage</h2>
    <p>
        Guests are responsible for taking care of places and their contents. The responsible party
        bears liability for any deliberate damage or damage resulting from misuse.
    </p>

    <h2>Harassment</h2>
    <p>
        Calm has zero tolerance for any form of harassment, threats, hatred, or discrimination.
        Immediate action is taken against violators, which may extend to permanently disabling the
        account and notifying the competent authorities where appropriate.
    </p>
@endif
@endsection
