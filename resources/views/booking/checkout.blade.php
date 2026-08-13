@extends('layouts.app')

{{-- Checkout summary PAGE — dates modal's «التالي» lands here. Status-page
     styling: clean white column, X to close back to the listing, fixed
     bottom CTA. Signed-in guests POST to book.store → Moyasar; signed-out
     guests get the login modal (it reloads this same URL, so the flow
     resumes right here already authenticated). --}}
@php
    use Illuminate\Support\Carbon;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $fmtTime = fn (?string $t) => $t ? Carbon::parse($t)->format('g:i A') : '—';
    $cover = $place->coverPhoto?->url;
    $city = $place->cityArea?->city;
@endphp

@section('title', ($isRtl ? 'ملخص الحجز' : 'Booking summary').' — Calm')

@section('body')
<div dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="min-h-screen bg-white {{ $fa }}"
     x-data="calmCheckout(@js([
        'placeId' => $place->id,
        'placeUrl' => route('places.show', $place),
        'submitUrl' => route('book.store', $place),
        'checkIn' => $checkIn,
        'checkOut' => $checkOut,
        'guests' => $guests,
        'maxGuests' => (int) ($place->max_guests ?: 1),
        'checkoutNextDay' => (bool) $place->checkout_next_day,
        'authed' => auth('api')->check(),
        'isRtl' => $isRtl,
     ]))" x-init="load()">

    {{-- Header — X closes the checkout back to the listing --}}
    <header class="sticky top-0 z-30" style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid #F1F1F1;">
        <div class="relative mx-auto w-full flex items-center justify-center" style="max-width: 560px; padding: 14px 20px;">
            <a href="{{ route('places.show', $place) }}" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
               class="calm-press calm-round absolute flex items-center justify-center bg-white text-black"
               style="inset-inline-start: 16px; width: 42px; height: 42px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 16px;">✕</a>
            <h1 class="font-bold text-black" style="font-size: 18px;">{{ $isRtl ? 'ملخص الحجز' : 'Booking summary' }}</h1>
        </div>
    </header>

    <main class="mx-auto w-full" style="max-width: 560px; padding: 24px 20px 170px;">

        {{-- Place row --}}
        <div class="flex items-center" style="gap: 14px;">
            <span class="shrink-0 overflow-hidden" style="width: 82px; height: 82px; border-radius: 22px; corner-shape: squircle; -webkit-corner-shape: squircle; background-color: #F3F4F6;">
                @if($cover)<img src="{{ $cover }}" alt="" class="w-full h-full object-cover">@endif
            </span>
            <div class="min-w-0">
                <div class="font-bold text-black truncate" style="font-size: 17px;">{{ $place->localized_title }}</div>
                <div class="truncate" style="font-size: 13px; color: #AAAAAA; margin-top: 4px;">
                    {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }} · {{ $city ? ($isRtl ? $city->name_ar : $city->name_en) : '' }}
                </div>
            </div>
        </div>

        {{-- Dates card --}}
        <div class="bg-white" style="margin-top: 22px; border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 30px rgba(0,0,0,0.05); padding: 4px 18px;">
            <div class="flex items-center justify-between" style="padding: 15px 0; border-bottom: 1px solid #F5F5F5; gap: 12px;">
                <span style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'الدخول' : 'Check-in' }}</span>
                <span class="font-bold text-black" style="font-size: 13.5px;"><span x-text="fmtDay(checkIn)"></span> · <bdi dir="ltr">{{ $fmtTime($place->check_in_time) }}</bdi></span>
            </div>
            <div class="flex items-center justify-between" style="padding: 15px 0; border-bottom: 1px solid #F5F5F5; gap: 12px;">
                <span style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'المغادرة' : 'Check-out' }}</span>
                <span class="font-bold text-black" style="font-size: 13.5px;"><span x-text="fmtDay(checkoutDay())"></span> · <bdi dir="ltr">{{ $fmtTime($place->check_out_time) }}</bdi></span>
            </div>
            <div class="flex items-center justify-between" style="padding: 15px 0;">
                <span style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'مدة الإقامة' : 'Stay' }}</span>
                <a href="{{ route('places.show', $place) }}#dates" class="font-bold text-black underline" style="font-size: 13px;">
                    <span x-text="stayLabel()"></span> — {{ $isRtl ? 'تغيير' : 'change' }}
                </a>
            </div>
        </div>

        {{-- Guests stepper --}}
        <div class="flex items-center justify-between" style="margin-top: 24px;">
            <div>
                <div class="font-bold text-black" style="font-size: 15px;">{{ $isRtl ? 'الضيوف' : 'Guests' }}</div>
                <div style="font-size: 12.5px; color: #AAAAAA; margin-top: 2px;">{{ $isRtl ? "بحد أقصى {$place->max_guests} ضيوف" : "Up to {$place->max_guests} guests" }}</div>
            </div>
            <div class="flex items-center" style="gap: 14px;">
                <button type="button" @click="guests = Math.max(1, guests - 1)"
                        class="calm-press calm-round flex items-center justify-center text-black"
                        style="width: 38px; height: 38px; border-radius: 50%; border: 1px solid #E9E9E9; font-size: 18px;">−</button>
                <span class="font-bold text-black tabular-nums" style="font-size: 16px; min-width: 22px; text-align: center;" x-text="guests"></span>
                <button type="button" @click="guests = Math.min({{ (int) ($place->max_guests ?: 1) }}, guests + 1)"
                        class="calm-press calm-round flex items-center justify-center text-black"
                        style="width: 38px; height: 38px; border-radius: 50%; border: 1px solid #E9E9E9; font-size: 18px;">+</button>
            </div>
        </div>

        {{-- Price breakdown --}}
        <div style="margin-top: 24px; border-top: 1px solid #F1F1F1; padding-top: 18px;">
            <div class="flex items-center justify-between" style="padding: 6px 0;">
                <span style="font-size: 14px; color: #AAAAAA;"><bdi dir="ltr">{{ number_format((int) $place->price) }} SR</bdi> × <span x-text="quote ? quote.days : '…'"></span> {{ $isRtl ? 'أيام' : 'days' }}</span>
                <span class="font-bold text-black tabular-nums" dir="ltr"><span x-text="fmtMoney(quote?.pricing.subtotal)"></span> SR</span>
            </div>
            <div class="flex items-center justify-between" style="padding: 6px 0;">
                <span style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'الضريبة' : 'VAT' }}</span>
                <span class="font-bold text-black tabular-nums" dir="ltr"><span x-text="fmtMoney(quote?.pricing.vat)"></span> SR</span>
            </div>
            <div class="flex items-center justify-between" style="padding: 12px 0; border-top: 1px solid #F1F1F1; margin-top: 8px;">
                <span class="font-bold text-black" style="font-size: 15px;">{{ $isRtl ? 'الإجمالي' : 'Total' }}</span>
                <span class="font-bold text-black tabular-nums" style="font-size: 18px;" dir="ltr"><span x-text="fmtMoney(quote?.pricing.total)"></span> SR</span>
            </div>
            <p x-show="quoteError" x-cloak style="font-size: 13px; color: #dc2626; margin-top: 8px;" x-text="quoteError"></p>
            <p x-show="submitError" x-cloak style="font-size: 13px; color: #dc2626; margin-top: 8px;" x-text="submitError"></p>
            <a x-show="quoteError" x-cloak href="{{ route('places.show', $place) }}#dates"
               class="calm-press inline-flex items-center font-bold text-black underline" style="font-size: 13px; margin-top: 6px;">
                {{ $isRtl ? 'اختيار تواريخ أخرى' : 'Pick different dates' }}
            </a>
        </div>
    </main>

    {{-- FIXED bottom CTA --}}
    <div class="fixed inset-x-0 bottom-0 z-30"
         style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 0 25px rgba(0,0,0,0.05);">
        <div class="mx-auto w-full" style="max-width: 560px; padding: 14px 20px calc(16px + env(safe-area-inset-bottom));">
            <button type="button" @click="proceed()" :disabled="!quote || submitting"
                    class="calm-press w-full font-bold text-white"
                    :style="'padding: 17px; border-radius: 18px; font-size: 16px; transition: opacity 0.2s; background-color: #F88379; box-shadow: 0 6px 12px rgba(248,131,121,0.3); opacity: ' + (quote && !submitting ? 1 : 0.5) + ';'">
                <span x-text="submitting
                    ? '{{ $isRtl ? 'جاري التحويل…' : 'Redirecting…' }}'
                    : (@js(auth('api')->check()) ? '{{ $isRtl ? 'التالي — الدفع' : 'Next — pay' }}' : '{{ $isRtl ? 'التالي — تسجيل الدخول' : 'Next — sign in' }}')"></span>
            </button>
            <p class="text-center" style="margin-top: 8px; font-size: 11.5px; color: #AAAAAA;">
                {{ $isRtl ? 'بالمتابعة، أوافق على شروط الحجز.' : 'By continuing, I agree to the booking terms.' }}
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
        guests: init.guests,
        quote: null, quoteError: '', quoteSeq: 0,
        submitting: false, submitError: '',

        load() {
            this.fetchQuote();
            this.$watch('guests', () => this.fetchQuote());
        },

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
            return n === 1 ? 'يوم واحد' : (n === 2 ? 'يومان' : `${n} أيام`);
        },
        fmtMoney(v) { return v == null ? '…' : Number(v).toLocaleString(); },

        async fetchQuote() {
            const seq = ++this.quoteSeq;
            this.quoteError = '';
            try {
                const q = new URLSearchParams({ check_in: this.checkIn, check_out: this.checkOut, guests: this.guests });
                const res = await fetch(`/api/places/${init.placeId}/quote?${q}`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                if (seq !== this.quoteSeq) return;
                const data = json.data;
                if (!res.ok || !data) { this.quote = null; this.quoteError = json.message || 'Error'; return; }
                if (!data.bookable) {
                    this.quote = null;
                    this.quoteError = !data.guests_ok
                        ? (init.isRtl ? `الحد الأقصى ${data.max_guests} ضيوف لهذا المكان.` : `This place hosts up to ${data.max_guests} guests.`)
                        : (init.isRtl ? 'التواريخ المختارة لم تعد متاحة.' : 'Those dates are no longer available.');
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
                    body: JSON.stringify({ check_in: this.checkIn, check_out: this.checkOut, guests: this.guests }),
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
