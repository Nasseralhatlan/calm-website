@extends('layouts.app')

{{-- «تأكيد و دفع» — checkout page the dates modal lands on. App parity: one
     simple white card (place row + rating, الوصول with تغيير, المغادرة with
     the next-day hint, stay/VAT/total rows), black fixed CTA. Signed-in
     guests POST to book.store → Moyasar; signed-out guests get the login
     modal, which reloads this same URL so the flow resumes authenticated. --}}
@php
    use Illuminate\Support\Carbon;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $fmtTime = fn (?string $t) => $t ? Carbon::parse($t)->format('g:i A') : '—';
    $cover = $place->coverPhoto?->url;
    $ratingCount = (int) ($place->published_reviews_count ?? 0);
    $ratingAvg = (float) ($place->published_reviews_avg_rate ?? 0);
    $isFavorite = $ratingAvg >= 4.8 && $ratingCount >= 2;
@endphp

@section('title', ($isRtl ? 'تأكيد و دفع' : 'Confirm & pay').' — Calm')

@section('body')
<div dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="min-h-screen {{ $fa }}" style="background-color: #FBFBFB;"
     x-data="calmCheckout(@js([
        'placeId' => $place->id,
        'submitUrl' => route('book.store', $place),
        'checkIn' => $checkIn,
        'checkOut' => $checkOut,
        'guests' => $guests,
        'checkoutNextDay' => (bool) $place->checkout_next_day,
        'authed' => auth('api')->check(),
        'isRtl' => $isRtl,
     ]))" x-init="load()">

    {{-- Header — X closes the checkout back to the listing --}}
    <header class="sticky top-0 z-30" style="background-color: rgba(251,251,251,0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="relative mx-auto w-full flex items-center justify-center" style="max-width: 560px; padding: 16px 20px;">
            <a href="{{ route('places.show', $place) }}" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
               class="calm-press calm-round absolute flex items-center justify-center bg-white text-black"
               style="inset-inline-start: 16px; width: 42px; height: 42px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 16px;">✕</a>
            <h1 class="font-bold text-black" style="font-size: 18px;">{{ $isRtl ? 'تأكيد و دفع' : 'Confirm & pay' }}</h1>
        </div>
    </header>

    <main class="mx-auto w-full" style="max-width: 560px; padding: 16px 20px 170px;">

        {{-- The one summary card (app style) --}}
        <div class="bg-white" style="border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 30px rgba(0,0,0,0.05); padding: 22px 20px;">

            {{-- Place row + rating --}}
            <div class="flex items-center" style="gap: 14px;">
                <span class="shrink-0 overflow-hidden" style="width: 84px; height: 84px; border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; background-color: #F3F4F6;">
                    @if($cover)<img src="{{ $cover }}" alt="" class="w-full h-full object-cover">@endif
                </span>
                <div class="min-w-0">
                    <div class="font-bold text-black truncate" style="font-size: 17px;">{{ $place->localized_title }}</div>
                    <div style="font-size: 13px; color: #1A1A1A; margin-top: 5px;">
                        <bdi dir="ltr">★{{ number_format($ratingAvg, 2) }} ({{ $ratingCount }})</bdi>@if($isFavorite) · {{ $isRtl ? 'مميز' : 'Guest favorite' }}@endif
                    </div>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; margin: 18px 0;"></div>

            {{-- الوصول + تغيير --}}
            <div class="flex items-center justify-between" style="gap: 12px;">
                <div class="min-w-0">
                    <div class="font-bold text-black" style="font-size: 16px;">{{ $isRtl ? 'الوصول' : 'Arrival' }}</div>
                    <div style="font-size: 13.5px; margin-top: 5px;"><span x-text="fmtDay(checkIn)"></span> · <bdi dir="ltr">{{ $fmtTime($place->check_in_time) }}</bdi></div>
                </div>
                <a href="{{ route('places.show', $place) }}#dates"
                   class="calm-press shrink-0 font-bold text-black" style="font-size: 13px; background-color: #F5F5F5; padding: 12px 20px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle;">
                    {{ $isRtl ? 'تغيير' : 'Change' }}
                </a>
            </div>

            <div style="border-top: 1px solid #F5F5F5; margin: 18px 0;"></div>

            {{-- المغادرة --}}
            <div>
                <div class="font-bold text-black" style="font-size: 16px;">{{ $isRtl ? 'المغادرة' : 'Departure' }}</div>
                <div style="font-size: 13.5px; margin-top: 5px;">
                    <span x-text="fmtDay(checkoutDay())"></span> · <bdi dir="ltr">{{ $fmtTime($place->check_out_time) }}</bdi>@if($place->checkout_next_day) ({{ $isRtl ? 'اليوم التالى' : 'next day' }})@endif
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; margin: 18px 0;"></div>

            {{-- Stay · nights + subtotal / VAT / total --}}
            <div class="flex items-center justify-between" style="padding: 4px 0;">
                <span style="font-size: 14.5px; color: #AAAAAA;">{{ $isRtl ? 'الإقامة' : 'Stay' }} · <span x-text="stayLabel()"></span></span>
                <span class="font-bold text-black tabular-nums" dir="ltr"><span x-text="fmtMoney(quote?.pricing.subtotal)"></span> SR</span>
            </div>
            <div class="flex items-center justify-between" style="padding: 10px 0 4px;">
                <span style="font-size: 14.5px; color: #AAAAAA;">{{ $isRtl ? 'ضريبة القيمة المضافة' : 'VAT' }} (<span x-text="vatPct()"></span>%)</span>
                <span class="font-bold text-black tabular-nums" dir="ltr"><span x-text="fmtMoney(quote?.pricing.vat)"></span> SR</span>
            </div>

            <div style="border-top: 1px solid #F5F5F5; margin: 16px 0 4px;"></div>

            <div class="flex items-center justify-between" style="padding: 10px 0 2px;">
                <span class="font-bold text-black" style="font-size: 16px;">{{ $isRtl ? 'الإجمالى' : 'Total' }}</span>
                <span class="font-bold text-black tabular-nums" style="font-size: 19px;" dir="ltr"><span x-text="fmtMoney(quote?.pricing.total)"></span> SR</span>
            </div>

            <p x-show="quoteError" x-cloak style="font-size: 13px; color: #dc2626; margin-top: 10px;" x-text="quoteError"></p>
            <p x-show="submitError" x-cloak style="font-size: 13px; color: #dc2626; margin-top: 10px;" x-text="submitError"></p>
            <a x-show="quoteError" x-cloak href="{{ route('places.show', $place) }}#dates"
               class="calm-press inline-flex items-center font-bold text-black underline" style="font-size: 13px; margin-top: 6px;">
                {{ $isRtl ? 'اختيار تواريخ أخرى' : 'Pick different dates' }}
            </a>
        </div>
    </main>

    {{-- FIXED bottom CTA — black, app style --}}
    <div class="fixed inset-x-0 bottom-0 z-30"
         style="background-color: rgba(251,251,251,0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="mx-auto w-full" style="max-width: 560px; padding: 14px 20px calc(16px + env(safe-area-inset-bottom));">
            <button type="button" @click="proceed()" :disabled="!quote || submitting"
                    class="calm-press w-full font-bold text-white"
                    :style="'padding: 18px; border-radius: 20px; font-size: 16px; transition: opacity 0.2s; background-color: #1A1A1A; opacity: ' + (quote && !submitting ? 1 : 0.4) + ';'">
                <span x-text="submitting
                    ? '{{ $isRtl ? 'جاري التحويل…' : 'Redirecting…' }}'
                    : (@js(auth('api')->check()) ? '{{ $isRtl ? 'متابعة للدفع' : 'Continue to pay' }}' : '{{ $isRtl ? 'تسجيل الدخول والمتابعة' : 'Sign in to continue' }}')"></span>
            </button>
            <p class="text-center" style="margin-top: 8px; font-size: 11.5px; color: #AAAAAA;">
                {{ $isRtl ? 'بالضغط، أوافق على شروط الحجز.' : 'By tapping, I agree to the booking terms.' }}
            </p>
        </div>
    </div>
</div>

<script>
function calmCheckout(init) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    // Parse 'YYYY-MM-DD' as a LOCAL date — new Date(str) is UTC midnight and
    // shifts a day for viewers west of UTC.
    const parseD = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const gregFmt = new Intl.DateTimeFormat(init.isRtl ? 'ar' : 'en', { weekday: 'long', day: 'numeric', month: 'long' });

    return {
        checkIn: init.checkIn,
        checkOut: init.checkOut,
        quote: null, quoteError: '', quoteSeq: 0,
        submitting: false, submitError: '',

        load() { this.fetchQuote(); },

        fmtDay(dateStr) {
            if (!dateStr) return '';
            try { return gregFmt.format(parseD(dateStr)); } catch (e) { return dateStr; }
        },
        checkoutDay() {
            const d = parseD(this.checkOut);
            if (init.checkoutNextDay) d.setDate(d.getDate() + 1);
            return iso(d);
        },
        stayLabel() {
            const n = this.quote
                ? this.quote.days
                : Math.round((parseD(this.checkOut) - parseD(this.checkIn)) / 86400000) + 1;
            if (!init.isRtl) return n === 1 ? '1 day' : `${n} days`;
            return n === 1 ? '1 يوم' : (n === 2 ? 'يومان' : `${n} أيام`);
        },
        fmtMoney(v) { return v == null ? '…' : Number(v).toLocaleString(); },
        vatPct() {
            if (!this.quote || !this.quote.pricing.subtotal) return 15;
            return Math.round((this.quote.pricing.vat / this.quote.pricing.subtotal) * 100);
        },

        async fetchQuote() {
            const seq = ++this.quoteSeq;
            this.quoteError = '';
            try {
                const q = new URLSearchParams({ check_in: this.checkIn, check_out: this.checkOut, guests: init.guests });
                const res = await fetch(`/api/places/${init.placeId}/quote?${q}`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                if (seq !== this.quoteSeq) return;
                const data = json.data;
                if (!res.ok || !data) { this.quote = null; this.quoteError = json.message || 'Error'; return; }
                if (!data.bookable) {
                    this.quote = null;
                    this.quoteError = init.isRtl ? 'التواريخ المختارة لم تعد متاحة.' : 'Those dates are no longer available.';
                    return;
                }
                this.quote = data;
            } catch (e) { if (seq === this.quoteSeq) this.quoteError = init.isRtl ? 'تعذر حساب السعر — حاول مجدداً.' : 'Could not fetch the price — try again.'; }
        },

        proceed() {
            if (!this.quote || this.submitting) return;
            if (!init.authed) {
                // The login modal reloads this same URL — the flow resumes
                // here already signed in, dates intact in the query string.
                window.dispatchEvent(new CustomEvent('calm-open-login'));
                return;
            }
            this.submit();
        },
        async submit() {
            this.submitting = true; this.submitError = '';
            try {
                const res = await fetch(init.submitUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ check_in: this.checkIn, check_out: this.checkOut, guests: init.guests }),
                });
                const json = await res.json();
                if (!res.ok) {
                    this.submitError = json.data?.errors
                        ? Object.values(json.data.errors).flat().join(' ')
                        : (json.message || (init.isRtl ? 'تعذر إنشاء الحجز.' : 'Could not create the booking.'));
                    this.submitting = false;
                    return;
                }
                window.location.href = json.payment_url;
            } catch (e) {
                this.submitError = init.isRtl ? 'حدث خطأ — حاول مجدداً.' : 'Something went wrong — try again.';
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
