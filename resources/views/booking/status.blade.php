@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $place = $booking->place;
    $city = $place?->cityArea?->city;
    $statusValue = $booking->booking_status->value;
    $confirmedStates = ['confirmed', 'completed'];
    $deadStates = ['expired', 'canceled_by_guest', 'canceled_by_host', 'canceled_by_admin'];
@endphp

@section('title', $isRtl ? 'حالة الحجز' : 'Booking status')

@section('body')
<div dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="min-h-screen flex items-center justify-center {{ $fa }}" style="background-color: #F8F8F8; padding: 20px;"
     x-data="bookingStatus({
        pollUrl: '/api/bookings/{{ $booking->id }}/payment-status',
        state: @js(in_array($statusValue, $confirmedStates, true) ? 'confirmed' : (in_array($statusValue, $deadStates, true) ? 'dead' : 'pending')),
     })" x-init="start()">

    <div class="bg-white w-full text-center" style="max-width: 430px; padding: 30px 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.06);">

        {{-- ── Checking ── --}}
        <template x-if="state === 'pending'">
            <div>
                <svg class="calm-spinner mx-auto" width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#F88379" stroke-width="3" stroke-linecap="round">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M22 12a10 10 0 0 1-10 10"/>
                </svg>
                <h1 class="font-bold text-[#222]" style="font-size: 20px; margin-top: 16px;">{{ $isRtl ? 'جارٍ تأكيد الدفع…' : 'Confirming your payment…' }}</h1>
                <p class="text-[#717171] text-[14px]" style="margin-top: 6px;">{{ $isRtl ? 'لحظات ويكتمل حجزك.' : 'Just a moment while we confirm your booking.' }}</p>
            </div>
        </template>

        {{-- ── Confirmed ── --}}
        <template x-if="state === 'confirmed'">
            <div>
                <div style="font-size: 46px; line-height: 1;">✅</div>
                <h1 class="font-bold text-[#16a34a]" style="font-size: 22px; margin-top: 12px;">{{ $isRtl ? 'تم تأكيد حجزك' : 'Booking confirmed' }}</h1>
                <div class="bg-[#fafafa] text-start" style="margin-top: 18px; padding: 16px 18px; border-radius: 18px;">
                    <p class="font-bold text-[#222]" style="font-size: 16px;">{{ $place?->title }}</p>
                    <p class="text-[#717171] text-[13px]" style="margin-top: 2px;">
                        {{ $isRtl ? $place?->type?->name_ar : $place?->type?->name_en }}
                        @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
                    </p>
                    <div class="text-[14px] text-[#222]" style="margin-top: 12px; line-height: 2;">
                        <div>📅 {{ $booking->start_date?->toDateString() }} ← {{ $booking->end_date?->toDateString() }}</div>
                        <div>👤 {{ $booking->guests }} {{ $isRtl ? 'ضيوف' : 'guests' }}</div>
                        <div>💳 <span dir="ltr">SR {{ number_format($booking->total_amount / 100, 2) }}</span> {{ $isRtl ? '(مدفوع)' : '(paid)' }}</div>
                        <div class="font-bold">🔖 {{ $booking->reference }}</div>
                    </div>
                </div>
                <p class="text-[#717171] text-[13px]" style="margin-top: 16px; line-height: 1.9;">
                    {{ $isRtl
                        ? 'وصلتك رسالة تأكيد نصية. حمّل تطبيق كالم وسجّل بنفس رقم جوالك لمتابعة حجزك والتواصل.'
                        : 'A confirmation SMS is on its way. Download the Calm app and sign in with the same number to manage your booking.' }}
                </p>
            </div>
        </template>

        {{-- ── Not completed ── --}}
        <template x-if="state === 'dead'">
            <div>
                <div style="font-size: 46px; line-height: 1;">⏳</div>
                <h1 class="font-bold text-[#222]" style="font-size: 20px; margin-top: 12px;">{{ $isRtl ? 'لم يكتمل الحجز' : 'Booking not completed' }}</h1>
                <p class="text-[#717171] text-[14px]" style="margin-top: 6px;">
                    {{ $isRtl ? 'انتهت صلاحية الحجز أو أُلغي قبل إتمام الدفع.' : 'The booking expired or was cancelled before payment completed.' }}
                </p>
                @if($place)
                    <a href="{{ route('book.show', $place) }}"
                       class="inline-block font-bold text-white bg-[#F88379] hover:bg-[#f56b60]"
                       style="margin-top: 18px; padding: 13px 28px; border-radius: 14px;">
                        {{ $isRtl ? 'احجز من جديد' : 'Book again' }}
                    </a>
                @endif
            </div>
        </template>
    </div>
</div>

<script>
function bookingStatus(init) {
    return {
        state: init.state,
        tries: 0,
        start() {
            if (this.state !== 'pending') return;
            const tick = async () => {
                this.tries++;
                try {
                    const res = await fetch(init.pollUrl, { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    const s = json.data?.status;
                    if (['confirmed', 'completed'].includes(s)) { this.state = 'confirmed'; return; }
                    if (['expired', 'canceled_by_guest', 'canceled_by_host', 'canceled_by_admin'].includes(s)) { this.state = 'dead'; return; }
                } catch (e) { /* keep polling */ }
                // Poll ~3s for 2 minutes, then slow to 10s — webhook usually
                // lands within seconds of the redirect.
                setTimeout(tick, this.tries < 40 ? 3000 : 10000);
            };
            tick();
        },
    };
}
</script>
@endsection
