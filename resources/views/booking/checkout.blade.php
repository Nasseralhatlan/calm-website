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
               @click="window.calmTrack?.('checkout', 'cancel', { place_id: @js($place->id) })"
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
            {{-- Promo code — Calm-funded discount off the stay (before VAT) --}}
            <div x-show="quote?.pricing.discount > 0" x-cloak class="flex items-center justify-between" style="padding: 10px 0 4px;">
                <span style="font-size: 14.5px; color: #AAAAAA;">
                    {{ $isRtl ? 'خصم' : 'Promo' }} <bdi dir="ltr" class="font-bold" style="color: #059669;" x-text="appliedCode"></bdi>
                    <button type="button" @click="removePromo()" class="calm-press underline" style="font-size: 12.5px; color: #AAAAAA; margin-inline-start: 6px;">{{ $isRtl ? 'إزالة' : 'Remove' }}</button>
                </span>
                <span class="font-bold tabular-nums" style="color: #059669;" dir="ltr">− <span x-text="fmtMoney(quote?.pricing.discount)"></span> SR</span>
            </div>
            <div x-show="quote?.pricing.discount > 0" x-cloak class="flex items-center justify-between" style="padding: 10px 0 4px;">
                <span style="font-size: 14.5px; color: #AAAAAA;">{{ $isRtl ? 'بعد الخصم' : 'After discount' }}</span>
                <span class="font-bold text-black tabular-nums" dir="ltr"><span x-text="fmtMoney(quote ? Math.round((quote.pricing.subtotal - quote.pricing.discount) * 100) / 100 : null)"></span> SR</span>
            </div>
            <div x-show="!appliedCode" style="padding: 10px 0 4px;">
                <button type="button" x-show="!promoOpen" @click="promoOpen = true; $nextTick(() => $refs.promo.focus())"
                        class="calm-press font-bold text-black underline" style="font-size: 13.5px;">
                    {{ $isRtl ? 'لديك كود خصم؟' : 'Have a promo code?' }}
                </button>
                <form x-show="promoOpen" x-cloak @submit.prevent="applyPromo()" class="flex items-center" style="gap: 8px;">
                    <input x-ref="promo" x-model="promoInput" type="text" maxlength="40" dir="ltr" autocomplete="off"
                           placeholder="{{ $isRtl ? 'أدخل الكود' : 'Enter code' }}"
                           class="flex-1 min-w-0 uppercase tracking-wider focus:outline-none"
                           style="font-size: 14.5px; background-color: #F5F5F5; padding: 12px 14px; border-radius: 14px;">
                    <button type="submit" :disabled="!promoInput.trim() || promoBusy"
                            class="calm-press shrink-0 font-bold text-white"
                            :style="'font-size: 13px; background-color: #1A1A1A; padding: 12px 18px; border-radius: 14px; opacity: ' + (promoInput.trim() && !promoBusy ? 1 : 0.4)">
                        {{ $isRtl ? 'تطبيق' : 'Apply' }}
                    </button>
                </form>
                <p x-show="promoError" x-cloak style="font-size: 12.5px; color: #dc2626; margin-top: 6px;" x-text="promoError"></p>
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
    // The applied code survives the login-modal reload of this page.
    const promoKey = `calm.promo.${init.placeId}`;
    const store = {
        get() { try { return sessionStorage.getItem(promoKey) || ''; } catch (e) { return ''; } },
        set(v) { try { v ? sessionStorage.setItem(promoKey, v) : sessionStorage.removeItem(promoKey); } catch (e) {} },
    };
    const promoReasons = init.isRtl ? {
        not_found: 'الكود غير صحيح.',
        inactive: 'انتهت صلاحية هذا الكود.',
        expired: 'انتهت صلاحية هذا الكود.',
        not_started: 'هذا الكود غير مفعّل بعد.',
        exhausted: 'تم استخدام هذا الكود بالكامل.',
        already_used: 'سبق أن استخدمت هذا الكود.',
        min_stay: 'قيمة الإقامة أقل من الحد الأدنى لهذا الكود',
    } : {
        not_found: "That code isn't valid.",
        inactive: 'This code has expired.',
        expired: 'This code has expired.',
        not_started: "This code isn't active yet.",
        exhausted: 'This code has been fully used.',
        already_used: "You've already used this code.",
        min_stay: "This stay is below the code's minimum",
    };

    return {
        checkIn: init.checkIn,
        checkOut: init.checkOut,
        quote: null, quoteError: '', quoteSeq: 0,
        submitting: false, submitError: '',
        promoOpen: false, promoInput: '', promoBusy: false, promoError: '', appliedCode: '',

        load() {
            const saved = store.get();
            if (saved) { this.promoInput = saved; this.promoOpen = true; this.appliedCode = saved; }
            // Fired once the quote lands, not on paint — total_sar is the whole
            // point of the event and it doesn't exist until then.
            this.fetchQuote().then(() => {
                window.calmTrackOnce?.('view', 'checkout', {
                    place_id: init.placeId,
                    total_sar: this.quote?.pricing?.total ?? null,
                    nights: this.stayNights(),
                });
            });
        },

        stayNights() {
            return Math.round((parseD(this.checkOut) - parseD(this.checkIn)) / 86400000) + 1;
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
            return n === 1 ? '1 يوم' : (n === 2 ? 'يومان' : `${n} أيام`);
        },
        fmtMoney(v) { return v == null ? '…' : Number(v).toLocaleString(); },
        vatPct() {
            return this.quote?.pricing.vat_percentage ?? 15;
        },

        async applyPromo() {
            const code = this.promoInput.trim().toUpperCase();
            if (!code || this.promoBusy) return;
            this.promoBusy = true;
            this.appliedCode = code;
            await this.fetchQuote();
            this.promoBusy = false;
        },
        removePromo() {
            this.appliedCode = ''; this.promoInput = ''; this.promoError = '';
            store.set('');
            this.fetchQuote();
        },
        // Settle the promo state from the quote's verdict.
        readPromo(data) {
            const promo = data.promo;
            if (!promo) return;
            if (promo.applied) { this.promoError = ''; store.set(promo.code); return; }
            let msg = promoReasons[promo.reason] || promo.message || '';
            if (promo.reason === 'min_stay' && promo.min_stay) msg += ` (${Number(promo.min_stay).toLocaleString()} SR).`;
            this.promoError = msg;
            this.appliedCode = '';
            store.set('');
        },

        async fetchQuote() {
            const seq = ++this.quoteSeq;
            this.quoteError = '';
            try {
                const q = new URLSearchParams({ check_in: this.checkIn, check_out: this.checkOut, guests: init.guests });
                if (this.appliedCode) q.set('promo_code', this.appliedCode);
                const res = await fetch(`/api/places/${init.placeId}/quote?${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const json = await res.json();
                if (seq !== this.quoteSeq) return;
                const data = json.data;
                if (!res.ok || !data) { this.quote = null; this.quoteError = json.message || 'Error'; return; }
                if (!data.bookable) {
                    this.quote = null;
                    this.quoteError = init.isRtl ? 'التواريخ المختارة لم تعد متاحة.' : 'Those dates are no longer available.';
                    return;
                }
                this.readPromo(data);
                this.quote = data;
            } catch (e) { if (seq === this.quoteSeq) this.quoteError = init.isRtl ? 'تعذر حساب السعر — حاول مجدداً.' : 'Could not fetch the price — try again.'; }
        },

        proceed() {
            if (!this.quote || this.submitting) return;
            // click:pay — they committed; next stop is Moyasar (or the login modal).
            window.calmTrack?.('click', 'pay', {
                place_id: init.placeId,
                total_sar: this.quote?.pricing?.total,
            });
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
                    body: JSON.stringify({
                        check_in: this.checkIn, check_out: this.checkOut, guests: init.guests,
                        promo_code: this.appliedCode || null,
                    }),
                });
                const json = await res.json();
                if (!res.ok && json.data?.errors?.promo_code) {
                    // The code stopped applying between quote and book (last
                    // use taken, expired) — drop it and re-quote at full price.
                    this.submitting = false;
                    this.removePromo();
                    this.promoOpen = true;
                    this.promoError = init.isRtl ? 'لم يعد هذا الكود صالحاً — تم تحديث السعر.' : 'That code no longer applies — the price was updated.';
                    return;
                }
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
