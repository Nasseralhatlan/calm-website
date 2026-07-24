@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $city = $place->cityArea?->city;
    $viewer = auth('api')->user();
    $init = [
        'placeId' => $place->id,
        'maxGuests' => (int) ($place->max_guests ?? 1),
        'authed' => $viewer !== null,
        'hasName' => $viewer !== null && trim((string) $viewer->name) !== '',
        'submitUrl' => route('book.store', $place),
        'isRtl' => $isRtl,
    ];
@endphp

@section('title', ($place->title ?: 'Calm').' · '.($isRtl ? 'احجز الآن' : 'Book now'))

@section('body')
<div x-data="bookingFunnel(@js($init))" x-init="load()" dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
     class="min-h-screen {{ $fa }}" style="background-color: #F8F8F8;">

    <main class="mx-auto w-full" style="max-width: 560px; padding: 16px 16px 120px;">

        {{-- ── Compact place header ── --}}
        <div class="bg-white overflow-hidden" style="border-radius: 24px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.06);">
            @if($place->coverPhoto?->url)
                <img src="{{ $place->coverPhoto->url }}" alt="" class="w-full object-cover" style="height: 180px;">
            @endif
            <div style="padding: 16px 18px;">
                <h1 class="font-bold text-[#222]" style="font-size: 20px;">{{ $place->title }}</h1>
                <p class="text-[#717171]" style="font-size: 13px; margin-top: 3px;">
                    {{ $place->type?->icon }} {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }}
                    @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
                    · <span class="font-bold text-[#222]" dir="ltr">SR {{ number_format($place->price) }}</span> {{ $isRtl ? '/ الليلة' : '/ night' }}
                </p>
            </div>
        </div>

        {{-- ── THE HERO: big date-range calendar ── --}}
        <div class="bg-white" style="border-radius: 24px; padding: 18px; margin-top: 14px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.06);">
            <h2 class="font-bold text-[#222]" style="font-size: 16px; margin-bottom: 4px;">{{ $isRtl ? 'اختر تواريخ إقامتك' : 'Pick your dates' }}</h2>
            <p class="text-[12px] text-[#999]" x-show="!checkIn">{{ $isRtl ? 'اضغط على يوم الوصول ثم يوم المغادرة.' : 'Tap check-in day, then checkout day.' }}</p>
            <p class="text-[13px] text-[#222] font-semibold" x-show="checkIn && !checkOut" x-cloak>{{ $isRtl ? 'الآن اختر يوم المغادرة' : 'Now pick your checkout day' }}</p>

            <div class="flex items-center justify-between" style="margin-top: 12px;">
                <button type="button" @click="prevMonth()" :disabled="!canGoPrev()"
                        class="w-10 h-10 inline-flex items-center justify-center font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec] disabled:opacity-30"
                        style="border-radius: 12px;"><span class="rtl:scale-x-[-1] inline-block">‹</span></button>
                <span class="font-bold text-[#222] text-[15px]" x-text="monthLabel()"></span>
                <button type="button" @click="nextMonth()"
                        class="w-10 h-10 inline-flex items-center justify-center font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec]"
                        style="border-radius: 12px;"><span class="rtl:scale-x-[-1] inline-block">›</span></button>
            </div>

            <div class="grid grid-cols-7 text-center text-[11px] text-[#999]" style="margin-top: 12px; gap: 2px;">
                <template x-for="d in dayNames()"><span x-text="d"></span></template>
            </div>
            <div class="grid grid-cols-7 text-center" style="gap: 2px; margin-top: 4px;">
                <template x-for="cell in monthCells()" :key="cell.key">
                    <button type="button"
                            x-text="cell.day || ''"
                            :disabled="!cell.day || cell.disabled"
                            @click="pickDay(cell.date)"
                            class="tabular-nums select-none"
                            :class="dayClass(cell)"
                            style="aspect-ratio: 1; border-radius: 12px; font-size: 15px;"></button>
                </template>
            </div>

            {{-- Guests + live total --}}
            <div class="flex items-center justify-between border-t border-[#f0f0f0]" style="margin-top: 16px; padding-top: 14px;">
                <span class="text-[14px] font-semibold text-[#222]">{{ $isRtl ? 'عدد الضيوف' : 'Guests' }}</span>
                <div class="inline-flex items-center" style="gap: 12px;">
                    <button type="button" @click="guests = Math.max(1, guests - 1)"
                            class="w-9 h-9 font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec]" style="border-radius: 10px;">−</button>
                    <span class="font-bold text-[#222] tabular-nums" style="min-width: 22px; text-align: center;" x-text="guests"></span>
                    <button type="button" @click="guests = Math.min(maxGuests, guests + 1)"
                            class="w-9 h-9 font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec]" style="border-radius: 10px;">+</button>
                </div>
            </div>

            <div x-show="quote" x-cloak class="flex items-center justify-between bg-[#fafafa]" style="margin-top: 12px; padding: 12px 14px; border-radius: 14px;">
                <span class="text-[13px] text-[#717171]">
                    <span x-text="nightsLabel()"></span> · {{ $isRtl ? 'شامل الضريبة' : 'VAT included' }}
                </span>
                <span class="font-bold text-[#222] tabular-nums" dir="ltr">SR <span x-text="quote ? Number(quote.pricing.total).toLocaleString() : ''"></span></span>
            </div>
            <p x-show="quoteError" x-cloak class="text-[13px] text-[#dc2626]" style="margin-top: 10px;" x-text="quoteError"></p>
        </div>

        {{-- ── Inline OTP login (hidden when already signed in) ── --}}
        <div x-show="!authed" x-cloak class="bg-white" style="border-radius: 24px; padding: 18px; margin-top: 14px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.06);">
            <h2 class="font-bold text-[#222]" style="font-size: 16px;">{{ $isRtl ? 'بياناتك' : 'Your details' }}</h2>

            <template x-if="!otpSent">
                <div>
                    <input type="text" x-model="name" placeholder="{{ $isRtl ? 'الاسم' : 'Your name' }}"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                           style="padding: 13px 16px; border-radius: 14px; margin-top: 12px;">
                    <input type="tel" x-model="phone" placeholder="5XXXXXXXX" dir="ltr" inputmode="numeric"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                           style="padding: 13px 16px; border-radius: 14px; margin-top: 10px;">
                    <button type="button" @click="requestOtp()" :disabled="authBusy || !name.trim() || normPhone().length !== 9"
                            class="w-full font-bold text-white bg-[#222] hover:bg-black disabled:bg-[#dddddd]"
                            style="padding: 13px; border-radius: 14px; margin-top: 12px;">
                        <span x-show="!authBusy">{{ $isRtl ? 'أرسل رمز التحقق' : 'Send verification code' }}</span>
                        <span x-show="authBusy" x-cloak>{{ $isRtl ? 'جارٍ الإرسال…' : 'Sending…' }}</span>
                    </button>
                </div>
            </template>

            <template x-if="otpSent">
                <div>
                    <p class="text-[13px] text-[#717171]" style="margin-top: 10px;">
                        {{ $isRtl ? 'أدخل الرمز المرسل إلى' : 'Enter the code sent to' }} <span dir="ltr" class="font-bold" x-text="normPhone()"></span>
                    </p>
                    <input type="text" x-model="otp" maxlength="6" inputmode="numeric" autocomplete="one-time-code" dir="ltr"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-center tracking-[8px] font-bold text-[20px] tabular-nums focus:outline-none"
                           style="padding: 13px 16px; border-radius: 14px; margin-top: 10px;">
                    <button type="button" @click="verifyOtp()" :disabled="authBusy || otp.length < 4"
                            class="w-full font-bold text-white bg-[#222] hover:bg-black disabled:bg-[#dddddd]"
                            style="padding: 13px; border-radius: 14px; margin-top: 12px;">
                        <span x-show="!authBusy">{{ $isRtl ? 'تأكيد' : 'Verify' }}</span>
                        <span x-show="authBusy" x-cloak>{{ $isRtl ? 'جارٍ التحقق…' : 'Verifying…' }}</span>
                    </button>
                    <button type="button" @click="otpSent = false; otp = ''" class="w-full text-[13px] text-[#717171] hover:text-[#222]" style="margin-top: 10px;">
                        {{ $isRtl ? 'تغيير الرقم' : 'Change number' }}
                    </button>
                </div>
            </template>

            <p x-show="authError" x-cloak class="text-[13px] text-[#dc2626]" style="margin-top: 10px;" x-text="authError"></p>
        </div>

        <p x-show="authed" x-cloak class="text-[13px] text-[#16a34a] font-semibold" style="margin-top: 14px; padding: 0 6px;">
            ✓ {{ $isRtl ? 'تم تسجيل دخولك' : 'You are signed in' }}
        </p>

        <p x-show="submitError" x-cloak class="text-[13px] text-[#dc2626] bg-[#fef2f2]" style="margin-top: 12px; padding: 12px 14px; border-radius: 14px;" x-text="submitError"></p>
    </main>

    {{-- ── Sticky pay bar ── --}}
    <div class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur border-t border-[#ebebeb]" style="padding: 12px 16px calc(12px + env(safe-area-inset-bottom));">
        <div class="mx-auto" style="max-width: 560px;">
            <button type="button" @click="submit()"
                    :disabled="!canSubmit()"
                    class="w-full font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.99] transition-all"
                    style="padding: 16px; border-radius: 16px; font-size: 16px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                <span x-show="!submitting" x-text="payLabel()"></span>
                <span x-show="submitting" x-cloak>{{ $isRtl ? 'جارٍ التحويل للدفع…' : 'Heading to payment…' }}</span>
            </button>
        </div>
    </div>
</div>

<script>
function bookingFunnel(init) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const AR_DAYS = ['أحد', 'إثن', 'ثلا', 'أرب', 'خمي', 'جمع', 'سبت'];
    const EN_DAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    const AR_MONTHS = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    const EN_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const todayIso = iso(new Date());

    return {
        placeId: init.placeId,
        maxGuests: init.maxGuests || 1,
        authed: init.authed,
        hasName: init.hasName,
        isRtl: init.isRtl,

        view: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
        unavailable: new Set(),
        checkIn: null,
        checkOut: null,
        guests: 1,
        quote: null,
        quoteError: '',
        quoteSeq: 0,

        name: '', phone: '', otp: '',
        otpSent: false, authBusy: false, authError: '',
        submitting: false, submitError: '',

        async load() {
            try {
                const res = await fetch(`/api/places/${this.placeId}/unavailable-dates`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                (json.data?.unavailable_dates || []).forEach((d) => this.unavailable.add(d));
            } catch (e) { /* calendar still works; server re-validates */ }
            this.$watch('guests', () => this.fetchQuote());
        },

        // ── calendar ──
        dayNames() { return this.isRtl ? AR_DAYS : EN_DAYS; },
        monthLabel() {
            const m = this.isRtl ? AR_MONTHS[this.view.getMonth()] : EN_MONTHS[this.view.getMonth()];
            return `${m} ${this.view.getFullYear()}`;
        },
        canGoPrev() {
            const now = new Date();
            return this.view > new Date(now.getFullYear(), now.getMonth(), 1);
        },
        prevMonth() { if (this.canGoPrev()) this.view = new Date(this.view.getFullYear(), this.view.getMonth() - 1, 1); },
        nextMonth() { this.view = new Date(this.view.getFullYear(), this.view.getMonth() + 1, 1); },
        monthCells() {
            const cells = [];
            const y = this.view.getFullYear(), m = this.view.getMonth();
            const first = new Date(y, m, 1);
            for (let i = 0; i < first.getDay(); i++) cells.push({ key: `pad-${i}`, day: null });
            const days = new Date(y, m + 1, 0).getDate();
            for (let d = 1; d <= days; d++) {
                const date = iso(new Date(y, m, d));
                cells.push({ key: date, day: d, date, disabled: date < todayIso || this.unavailable.has(date) });
            }
            return cells;
        },
        pickDay(date) {
            this.submitError = '';
            if (!this.checkIn || (this.checkIn && this.checkOut)) {
                this.checkIn = date; this.checkOut = null; this.quote = null; this.quoteError = '';
                return;
            }
            if (date <= this.checkIn) { this.checkIn = date; return; }
            // Every NIGHT between check-in and checkout must be free
            // (the checkout day itself may host someone else's check-in).
            if (!this.rangeFree(this.checkIn, date)) {
                this.quoteError = this.isRtl ? 'يوجد ليالٍ محجوزة ضمن المدى المختار — اختر تواريخ أخرى.' : 'Some nights in that range are booked — pick different dates.';
                this.checkIn = date; this.checkOut = null; this.quote = null;
                return;
            }
            this.checkOut = date;
            this.fetchQuote();
        },
        rangeFree(a, b) {
            const cur = new Date(a); const end = new Date(b);
            while (cur < end) { if (this.unavailable.has(iso(cur))) return false; cur.setDate(cur.getDate() + 1); }
            return true;
        },
        dayClass(cell) {
            if (!cell.day) return '';
            if (cell.disabled) return 'text-[#ccc] line-through cursor-not-allowed';
            if (cell.date === this.checkIn || cell.date === this.checkOut) return 'bg-[#222] text-white font-bold';
            if (this.checkIn && this.checkOut && cell.date > this.checkIn && cell.date < this.checkOut) return 'bg-[#f3f4f6] text-[#222] font-semibold';
            return 'text-[#222] hover:bg-[#f3f4f6]';
        },

        // ── quote ──
        async fetchQuote() {
            if (!this.checkIn || !this.checkOut) return;
            const seq = ++this.quoteSeq;
            this.quoteError = '';
            try {
                const q = new URLSearchParams({ check_in: this.checkIn, check_out: this.checkOut, guests: this.guests });
                const res = await fetch(`/api/places/${this.placeId}/quote?${q}`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                if (seq !== this.quoteSeq) return;
                const data = json.data;
                if (!res.ok || !data) { this.quote = null; this.quoteError = json.message || 'Error'; return; }
                if (!data.bookable) {
                    this.quote = null;
                    this.quoteError = !data.guests_ok
                        ? (this.isRtl ? `الحد الأقصى ${data.max_guests} ضيوف لهذا المكان.` : `This place hosts up to ${data.max_guests} guests.`)
                        : (this.isRtl ? 'التواريخ المختارة لم تعد متاحة.' : 'Those dates are no longer available.');
                    return;
                }
                this.quote = data;
            } catch (e) { if (seq === this.quoteSeq) this.quoteError = this.isRtl ? 'تعذر حساب السعر — حاول مجدداً.' : 'Could not fetch the price — try again.'; }
        },
        nightsLabel() {
            if (!this.quote) return '';
            const n = this.quote.days;
            if (!this.isRtl) return n === 1 ? '1 night' : `${n} nights`;
            return n === 1 ? 'ليلة واحدة' : (n === 2 ? 'ليلتان' : `${n} ليالٍ`);
        },

        // ── inline OTP auth (existing API endpoints; verify sets the JWT cookie) ──
        normPhone() {
            let d = (this.phone || '').replace(/\D+/g, '').replace(/^(?:00966|966)/, '').replace(/^0+/, '');
            return d;
        },
        async requestOtp() {
            this.authBusy = true; this.authError = '';
            try {
                const res = await fetch('/api/auth/otp/request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ phone: this.normPhone() }),
                });
                const json = await res.json();
                if (!res.ok) { this.authError = json.data?.errors?.phone?.[0] || json.message || 'Error'; return; }
                this.otpSent = true;
            } catch (e) { this.authError = this.isRtl ? 'تعذر إرسال الرمز.' : 'Could not send the code.'; }
            finally { this.authBusy = false; }
        },
        async verifyOtp() {
            this.authBusy = true; this.authError = '';
            try {
                const res = await fetch('/api/auth/otp/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ phone: this.normPhone(), otp: this.otp }),
                });
                const json = await res.json();
                if (!res.ok) { this.authError = this.isRtl ? 'رمز غير صحيح أو منتهي.' : 'Wrong or expired code.'; return; }
                // JWT cookie is set by the response; the booking POST rides on it.
                this.authed = true;
            } catch (e) { this.authError = this.isRtl ? 'تعذر التحقق.' : 'Verification failed.'; }
            finally { this.authBusy = false; }
        },

        // ── submit ──
        canSubmit() {
            return this.authed && this.checkIn && this.checkOut && this.quote && !this.submitting;
        },
        payLabel() {
            if (!this.checkIn || !this.checkOut) return this.isRtl ? 'اختر التواريخ أولاً' : 'Pick your dates first';
            if (!this.authed) return this.isRtl ? 'أكمل بياناتك أولاً' : 'Complete your details first';
            const total = this.quote ? ` · SR ${Number(this.quote.pricing.total).toLocaleString()}` : '';
            return (this.isRtl ? 'احجز وادفع' : 'Book & pay') + total;
        },
        async submit() {
            if (!this.canSubmit()) return;
            this.submitting = true; this.submitError = '';
            try {
                const res = await fetch(init.submitUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        check_in: this.checkIn,
                        check_out: this.checkOut,
                        guests: this.guests,
                        name: this.name.trim() || undefined,
                    }),
                });
                const json = await res.json();
                if (!res.ok) {
                    this.submitError = json.data?.errors ? Object.values(json.data.errors).flat().join(' ') : (json.message || (this.isRtl ? 'تعذر إنشاء الحجز.' : 'Could not create the booking.'));
                    this.submitting = false;
                    return;
                }
                window.location.href = json.payment_url;
            } catch (e) {
                this.submitError = this.isRtl ? 'حدث خطأ — حاول مجدداً.' : 'Something went wrong — try again.';
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
