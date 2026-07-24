@extends('layouts.app')

@php
    use Illuminate\Support\Carbon;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $place = $booking->place;
    $city = $place?->cityArea?->city;
    $fmtTime = fn (?string $t) => $t ? Carbon::parse($t)->format('g:i A') : '—';
    $statusValue = $booking->booking_status->value;
    $confirmedStates = ['confirmed', 'completed'];
    $deadStates = ['expired', 'canceled_by_guest', 'canceled_by_host', 'canceled_by_admin'];
    $ratingCount = (int) ($place?->published_reviews_count ?? 0);
    $ratingAvg = number_format((float) ($place?->published_reviews_avg_rate ?? 0), 2);
    $dateFmt = fn ($d) => $d?->locale($locale)->isoFormat('ddd، D MMMM');
@endphp

@section('title', $isRtl ? 'حالة الحجز' : 'Booking status')

@section('body')
<div dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="min-h-screen flex flex-col {{ $fa }}" style="background-color: #fff;"
     x-data="bookingStatus({
        pollUrl: '/api/bookings/{{ $booking->id }}/payment-status',
        state: @js(in_array($statusValue, $confirmedStates, true) ? 'confirmed' : (in_array($statusValue, $deadStates, true) ? 'dead' : 'pending')),
        successLottie: '/assets/lottie/booking-success.json',
        pendingLottie: '/assets/lottie/booking-pending.json',
     })" x-init="start()">

    <main class="flex-1 mx-auto w-full flex flex-col" style="max-width: 560px; padding: 40px 22px 120px;">

        {{-- ── Animation (static fallback shows unless Lottie mounts) ── --}}
        <div class="mx-auto relative" style="width: clamp(120px, 38vw, 170px); height: clamp(120px, 38vw, 170px);">
            <div x-ref="anim" class="absolute inset-0"></div>
            <div x-show="!animReady" class="absolute inset-0 flex items-center justify-center">
                <template x-if="state === 'pending'">
                    <svg class="calm-spinner" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#c9ced6" stroke-width="2.6" stroke-linecap="round">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.3"/><path d="M22 12a10 10 0 0 1-10 10"/>
                    </svg>
                </template>
                <template x-if="state === 'confirmed'">
                    <span class="flex items-center justify-center" style="width: 96px; height: 96px; border-radius: 999px; background-color: #28b463;">
                        <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5l4.5 4.5L19.5 7"/></svg>
                    </span>
                </template>
                <template x-if="state === 'dead'">
                    <span class="flex items-center justify-center" style="width: 96px; height: 96px; border-radius: 999px; background-color: #ffb703; color: #fff; font-size: 46px; font-weight: 800;">!</span>
                </template>
            </div>
        </div>

        {{-- ── Title + subtitle ── --}}
        <template x-if="state === 'pending'">
            <div class="text-center">
                <h1 class="font-bold text-[#222]" style="font-size: clamp(19px, 5.4vw, 24px);">{{ $isRtl ? 'جاري تأكيد الدفع…' : 'Confirming your payment…' }}</h1>
                <p class="text-[#717171]" style="font-size: clamp(13px, 3.8vw, 15px); margin-top: 8px; line-height: 1.9;">
                    {{ $isRtl ? 'لحظات ويكتمل حجزك. لا تغلق الصفحة.' : 'Just a moment while we confirm your booking. Keep this page open.' }}
                </p>
            </div>
        </template>

        <template x-if="state === 'confirmed'">
            <div class="text-center">
                <h1 class="font-bold text-[#222]" style="font-size: clamp(21px, 6vw, 26px);">{{ $isRtl ? 'تم تأكيد حجزك' : 'Booking confirmed' }}</h1>
                <p class="text-[#717171]" style="font-size: clamp(13px, 3.8vw, 15px); margin-top: 8px; line-height: 1.9;">
                    {{ $isRtl
                        ? 'سيتواصل معك المضيف قبل وصولك. حمّل تطبيق كالم وسجّل بنفس رقم جوالك لمتابعة حجزك.'
                        : 'Your host will reach out before your arrival. Download the Calm app and sign in with the same number to manage your booking.' }}
                </p>
            </div>
        </template>

        <template x-if="state === 'dead'">
            <div class="text-center">
                <h1 class="font-bold text-[#222]" style="font-size: clamp(19px, 5.4vw, 24px);">{{ $isRtl ? 'لم يكتمل الحجز' : 'Booking not completed' }}</h1>
                <p class="text-[#717171]" style="font-size: clamp(13px, 3.8vw, 15px); margin-top: 8px; line-height: 1.9;">
                    {{ $isRtl ? 'انتهت صلاحية الحجز أو أُلغي قبل إتمام الدفع.' : 'The booking expired or was cancelled before payment completed.' }}
                </p>
            </div>
        </template>

        {{-- ── Booking card (app-style) ── --}}
        <div x-show="state !== 'dead'" class="bg-white" style="border-radius: 26px; padding: 22px 20px; margin-top: 28px; box-shadow: 0px 12px 34px 0px rgba(0,0,0,0.08);">
            <div class="flex items-center" style="gap: 14px;">
                @if($place?->coverPhoto?->url)
                    <img src="{{ $place->coverPhoto->url }}" alt="" class="object-cover shrink-0" style="width: 84px; height: 84px; border-radius: 22px;">
                @endif
                <div class="min-w-0">
                    <p class="font-bold text-[#222] truncate" style="font-size: clamp(15px, 4.6vw, 18px);">{{ $place?->title }}</p>
                    <p class="text-[#717171] text-[13px]" style="margin-top: 4px;">
                        <span style="color: #f5b50a;">★</span> {{ $ratingAvg }} ({{ $ratingCount }})
                        @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
                    </p>
                </div>
            </div>

            <div class="text-[14px]" style="margin-top: 18px;">
                <div class="flex items-center justify-between" style="padding: 12px 0; border-bottom: 1px solid #f7f7f7;">
                    <span class="text-[#717171]">{{ $isRtl ? 'الوصول' : 'Arrival' }}</span>
                    <span class="font-semibold text-[#222]">{{ $dateFmt($booking->start_date) }} · <span dir="ltr">{{ $fmtTime($place?->check_in_time) }}</span></span>
                </div>
                <div class="flex items-center justify-between" style="padding: 12px 0; border-bottom: 1px solid #f7f7f7;">
                    <span class="text-[#717171]">{{ $isRtl ? 'المغادرة' : 'Departure' }}</span>
                    <span class="font-semibold text-[#222]">
                        {{ $dateFmt($booking->end_date) }} · <span dir="ltr">{{ $fmtTime($place?->check_out_time) }}</span>
                        @if($place?->checkout_next_day) ({{ $isRtl ? 'اليوم التالي' : 'next day' }}) @endif
                    </span>
                </div>
                <div class="flex items-center justify-between" style="padding: 12px 0; border-bottom: 1px solid #f7f7f7;">
                    <span class="text-[#717171]">{{ $isRtl ? 'الضيوف' : 'Guests' }}</span>
                    <span class="font-semibold text-[#222] tabular-nums">{{ $booking->guests }} {{ $isRtl ? 'ضيف' : 'guest(s)' }}</span>
                </div>
                <div class="flex items-center justify-between" style="padding: 12px 0;">
                    <span class="text-[#717171]">{{ $isRtl ? 'الإجمالي' : 'Total' }}</span>
                    <span class="font-bold text-[#222] tabular-nums" style="font-size: 16px;" dir="ltr">{{ number_format($booking->total_amount / 100) }} SR</span>
                </div>
            </div>
        </div>

        <div x-show="state === 'dead'" x-cloak class="text-center" style="margin-top: 24px;">
            @if($place)
                <a href="{{ route('book.show', $place) }}"
                   class="inline-block font-bold text-white bg-[#222] hover:bg-black"
                   style="padding: 15px 34px; border-radius: 18px;">
                    {{ $isRtl ? 'احجز من جديد' : 'Book again' }}
                </a>
            @endif
        </div>
    </main>

    {{-- ── Return home ── --}}
    <div class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur" style="padding: 12px 22px calc(16px + env(safe-area-inset-bottom)); box-shadow: 0 -8px 24px rgba(0,0,0,0.05);">
        <div class="mx-auto" style="max-width: 560px;">
            <a href="{{ route('landing') }}"
               class="block w-full text-center font-bold text-white bg-[#222] hover:bg-black active:scale-[0.99] transition-all"
               style="padding: 16px; border-radius: 18px; font-size: 16px;">
                {{ $isRtl ? 'العودة للرئيسية' : 'Return home' }}
            </a>
        </div>
    </div>
</div>

<script>
function bookingStatus(init) {
    return {
        state: init.state,
        tries: 0,
        anim: null,
        animReady: false,

        async start() {
            await this.mountAnim();
            if (this.state !== 'pending') return;
            const tick = async () => {
                this.tries++;
                try {
                    const res = await fetch(init.pollUrl, { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    const s = json.data?.status;
                    if (['confirmed', 'completed'].includes(s)) { this.toConfirmed(); return; }
                    if (['expired', 'canceled_by_guest', 'canceled_by_host', 'canceled_by_admin'].includes(s)) { this.toDead(); return; }
                } catch (e) { /* keep polling */ }
                // Poll ~3s for 2 minutes, then slow to 10s — the webhook
                // usually lands within seconds of the redirect.
                setTimeout(tick, this.tries < 40 ? 3000 : 10000);
            };
            tick();
        },

        // ── lottie: one file carries both the loader loop and the success pop.
        // The JSON is fetched inline (animationData) so playback starts
        // synchronously — no renderer events to depend on; the static icon
        // stays as fallback until the animation actually mounts. ──
        async mountAnim() {
            try {
                const [lottie, res] = await Promise.all([
                    window.loadLottie(),
                    fetch(this.state === 'dead' ? init.pendingLottie : init.successLottie),
                ]);
                const animationData = await res.json();
                if (this.anim) { this.anim.destroy(); this.anim = null; }
                this.anim = lottie.loadAnimation({
                    container: this.$refs.anim,
                    renderer: 'svg',
                    loop: this.state === 'pending',
                    autoplay: false,
                    animationData,
                });
                if (this.state === 'dead') this.anim.play();
                else if (this.state === 'confirmed') this.anim.playSegments([85, 145], true);
                else this.anim.playSegments([0, 85], true); // grey loader sweep only
                this.animReady = true;
            } catch (e) { /* fallback icon stays — the page works without the animation */ }
        },
        toConfirmed() {
            this.state = 'confirmed';
            if (this.anim && this.animReady) {
                this.anim.loop = false;
                this.anim.playSegments([85, 145], true);
            }
        },
        toDead() {
            this.state = 'dead';
            this.animReady = false;
            if (this.anim) { this.anim.destroy(); this.anim = null; }
            this.mountAnim();
        },
    };
}
</script>
@endsection
