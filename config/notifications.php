<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notification message templates  (EDIT TEXT HERE)
|--------------------------------------------------------------------------
|
| One place for every notification's title + body, in Arabic and English.
| Keep them short. They contain NO user-generated content (no place names) —
| only system values via placeholders:
|
|   {ref}     booking reference (e.g. CB-3QAKD4)
|   {dates}   stay date range, no times (e.g. "23 Jun 2026 – 25 Jun 2026")
|   {reason}  admin's rejection note (place_rejected only)
|   {code}    one-time verification code (otp only)
|
| Structure: <type>.<audience>. Cancellations notify both guest and host, so
| they have a `guest` and a `host` block. After editing, run:
|   php artisan config:clear   (local)   /   config:cache   (production)
|
*/

return [

    // One-time verification code (sign-in / sign-up). Flat shape — a single
    // line per channel, not title+body. The {code} stays in Latin digits so it
    // matches the phone keypad. Delivered in the user's locale (default ar).
    // The Arabic body must end with the exact token "رمز: {code}" — that
    // keyword+digits shape is what iOS one-time-code autofill detects; the
    // old "…هو: {code}" ending broke it.
    'otp' => [
        'sms_ar' => 'رمز التحقق الخاص بك في كالم، رمز: {code}',
        'sms_en' => 'Your Calm verification code is: {code}',
        'email_subject_ar' => 'رمز التحقق في كالم',
        'email_subject_en' => 'Your Calm verification code',
    ],

    'booking_confirmed' => [
        'guest' => [
            'title_ar' => 'تم تأكيد حجزك',
            'title_en' => 'Booking confirmed',
            'body_ar' => 'تم تأكيد حجزك بتاريخ {dates}. رقم الحجز: {ref}. لمزيد من التفاصيل، اطّلع على حجزك في تطبيق كالم.',
            'body_en' => 'Your booking is confirmed for {dates}. Ref: {ref}. For more details, view your booking in the Calm app.',
        ],
    ],

    'host_new_booking' => [
        'host' => [
            'title_ar' => 'حجز جديد',
            'title_en' => 'New booking',
            'body_ar' => 'لديك حجز جديد بتاريخ {dates} في تطبيق كالم. رقم الحجز: {ref}.',
            'body_en' => 'You have a new booking for {dates} in the Calm app. Ref: {ref}.',
        ],
    ],

    // The host's payout settled at the bank — tell them the money moved.
    'host_payout_paid' => [
        'host' => [
            'title_ar' => 'تم تحويل أرباحك',
            'title_en' => 'Your payout was sent',
            'body_ar' => 'تم تحويل {amount} ر.س عن الحجز {ref} إلى حسابك البنكي.',
            'body_en' => 'SR {amount} for booking {ref} was transferred to your bank account.',
        ],
    ],

    // A payout is due but the host has no bank IBAN on file. Nudged at most
    // once per day until they add it (then the payout fires automatically).
    'host_iban_needed' => [
        'host' => [
            'title_ar' => 'أضف الآيبان لاستلام أرباحك',
            'title_en' => 'Add your IBAN to get paid',
            'body_ar' => 'لديك دفعة بقيمة {amount} ر.س بانتظارك عن الحجز {ref}. أضف رقم الآيبان البنكي في تطبيق كالم لاستلامها تلقائياً.',
            'body_en' => 'A payout of SR {amount} for booking {ref} is waiting. Add your bank IBAN in the Calm app to receive it automatically.',
        ],
    ],

    // A connected external calendar (Airbnb/Gathern) imported an event over
    // dates an active Calm booking already holds — likely double-booked.
    'calendar_conflict' => [
        'host' => [
            'title_ar' => 'تعارض في التقويم',
            'title_en' => 'Calendar conflict',
            'body_ar' => 'حجز من تقويم خارجي مرتبط يتداخل مع حجزك {ref} بتاريخ {dates}. يرجى التحقق لتجنب الحجز المزدوج.',
            'body_en' => 'An external calendar event overlaps your booking {ref} for {dates}. Please check to avoid a double booking.',
        ],
    ],

    'booking_canceled_by_host' => [
        'guest' => [
            'title_ar' => 'تم إلغاء حجزك',
            'title_en' => 'Booking cancelled',
            'body_ar' => 'نعتذر، تم إلغاء حجزك من قِبل المضيف. رقم الحجز: {ref}.',
            'body_en' => 'Sorry, your booking was cancelled by the host. Ref: {ref}.',
        ],
        'host' => [
            'title_ar' => 'تم إلغاء الحجز',
            'title_en' => 'Booking cancelled',
            'body_ar' => 'تم إلغاء الحجز بناءً على طلبك. رقم الحجز: {ref}.',
            'body_en' => 'The booking was cancelled per your request. Ref: {ref}.',
        ],
    ],

    'booking_canceled_by_admin' => [
        'guest' => [
            'title_ar' => 'تم إلغاء حجزك',
            'title_en' => 'Booking cancelled',
            'body_ar' => 'تم إلغاء حجزك بناءً على طلبك. رقم الحجز: {ref}.',
            'body_en' => 'Your booking was cancelled as requested. Ref: {ref}.',
        ],
        'host' => [
            'title_ar' => 'تم إلغاء حجز',
            'title_en' => 'A booking was cancelled',
            'body_ar' => 'تم إلغاء حجز بناءً على طلب الضيف. رقم الحجز: {ref}.',
            'body_en' => 'A booking was cancelled at the guest\'s request. Ref: {ref}.',
        ],
    ],

    // Reserved (not currently wired).
    'booking_cancelled' => [
        'guest' => [
            'title_ar' => 'تم إلغاء حجزك',
            'title_en' => 'Booking cancelled',
            'body_ar' => 'تم إلغاء حجزك. رقم الحجز: {ref}.',
            'body_en' => 'Your booking was cancelled. Ref: {ref}.',
        ],
    ],

    'place_submitted' => [
        'host' => [
            'title_ar' => 'تم استلام مكانك',
            'title_en' => 'Place received',
            'body_ar' => 'تم استلام مكانك وهو قيد المراجعة. سنخبرك عند ظهوره في تطبيق كالم.',
            'body_en' => 'Your place was received and is under review. We\'ll let you know when it\'s live in the Calm app.',
        ],
    ],

    'place_approved' => [
        'host' => [
            'title_ar' => 'تمت الموافقة على مكانك',
            'title_en' => 'Place approved',
            'body_ar' => 'تمت الموافقة على مكانك وأصبح متاحاً في تطبيق كالم.',
            'body_en' => 'Your place was approved and is now live in the Calm app.',
        ],
    ],

    'place_rejected' => [
        'host' => [
            'title_ar' => 'مكانك يحتاج تعديلات',
            'title_en' => 'Place needs changes',
            'body_ar' => 'يحتاج مكانك إلى تعديلات: {reason}',
            'body_en' => 'Your place needs changes: {reason}',
        ],
        // Used when the admin gives no reason.
        'host_no_reason' => [
            'title_ar' => 'مكانك يحتاج تعديلات',
            'title_en' => 'Place needs changes',
            'body_ar' => 'يحتاج مكانك إلى بعض التعديلات قبل الموافقة.',
            'body_en' => 'Your place needs some changes before approval.',
        ],
    ],

];
