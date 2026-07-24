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
        'submitUrl' => route('book.store', $place),
        'isRtl' => $isRtl,
    ];
@endphp

@section('title', ($place->title ?: 'Calm').' · '.($isRtl ? 'احجز الآن' : 'Book now'))

@section('body')
<div x-data="bookingFunnel(@js($init))" x-init="load()" dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
     class="min-h-screen {{ $fa }}" style="background-color: #F8F8F8;">

    {{-- ── Calm header ── --}}
    <header class="w-full bg-white border-b border-[#ebebeb] sticky top-0 z-30 backdrop-blur" style="background-color: rgba(255,255,255,0.92);">
        <div class="px-5 h-16 flex items-center justify-between mx-auto" style="max-width: 560px;">
            <a href="{{ route('landing') }}" class="flex items-center">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-8 w-auto select-none" draggable="false">
            </a>
            <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                @csrf
                <button type="submit" class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}"
                        style="padding: 8px 12px; border-radius: 12px;">
                    {{ $locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </form>
        </div>
    </header>

    <main class="mx-auto w-full" style="max-width: 560px; padding: 18px 18px 130px;">

        {{-- ══ STEP 1 · place + dates ══ --}}
        <template x-if="step === 1">
            <div>
                {{-- Standalone image, fully rounded — info sits UNDER it, no card. --}}
                @if($place->coverPhoto?->url)
                    <img src="{{ $place->coverPhoto->url }}" alt=""
                         class="w-full object-cover" style="height: 210px; border-radius: 24px;">
                @endif
                <div style="padding: 14px 6px 0;">
                    <h1 class="font-bold text-[#222]" style="font-size: 21px;">{{ $place->title }}</h1>
                    <p class="text-[#717171]" style="font-size: 14px; margin-top: 3px;">
                        {{ $place->type?->icon }} {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }}
                        @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
                    </p>
                    <p class="font-bold text-[#222]" style="font-size: 15px; margin-top: 4px;">
                        <span dir="ltr">SR {{ number_format($place->price) }}</span> <span class="text-[#717171] font-normal text-[13px]">{{ $isRtl ? '/ الليلة' : '/ night' }}</span>
                    </p>
                </div>

                {{-- Dates --}}
                <div class="bg-white" style="border-radius: 24px; padding: 18px; margin-top: 18px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.06);">
                    <h2 class="font-bold text-[#222]" style="font-size: 16px;">{{ $isRtl ? 'اختر تواريخ إقامتك' : 'Pick your dates' }}</h2>
                    <p class="text-[12px] text-[#999]" style="margin-top: 2px;" x-show="!checkIn">{{ $isRtl ? 'اضغط على يوم الوصول ثم يوم المغادرة.' : 'Tap check-in day, then checkout day.' }}</p>
                    <p class="text-[13px] font-semibold text-[#F88379]" style="margin-top: 2px;" x-show="checkIn && !checkOut" x-cloak>{{ $isRtl ? 'الآن اختر يوم المغادرة' : 'Now pick your checkout day' }}</p>

                    <div class="flex items-center justify-between" style="margin-top: 14px;">
                        <button type="button" @click="prevMonth()" :disabled="!canGoPrev()"
                                class="w-10 h-10 inline-flex items-center justify-center font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec] disabled:opacity-30"
                                style="border-radius: 999px;"><span class="rtl:scale-x-[-1] inline-block">‹</span></button>
                        <span class="font-bold text-[#222] text-[15px]" x-text="monthLabel()"></span>
                        <button type="button" @click="nextMonth()"
                                class="w-10 h-10 inline-flex items-center justify-center font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec]"
                                style="border-radius: 999px;"><span class="rtl:scale-x-[-1] inline-block">›</span></button>
                    </div>

                    <div class="grid grid-cols-7 text-center text-[11px] text-[#999]" style="margin-top: 12px; gap: 4px;">
                        <template x-for="d in dayNames()"><span x-text="d"></span></template>
                    </div>
                    {{-- Circular day cells --}}
                    <div class="grid grid-cols-7 text-center" style="gap: 4px; margin-top: 6px;">
                        <template x-for="cell in monthCells()" :key="cell.key">
                            <button type="button"
                                    x-text="cell.day || ''"
                                    :disabled="!cell.day || cell.disabled"
                                    @click="pickDay(cell.date)"
                                    class="tabular-nums select-none transition-colors"
                                    :class="dayClass(cell)"
                                    style="aspect-ratio: 1; border-radius: 999px; font-size: 14px;"></button>
                        </template>
                    </div>

                    <div class="flex items-center justify-between border-t border-[#f0f0f0]" style="margin-top: 16px; padding-top: 14px;">
                        <span class="text-[14px] font-semibold text-[#222]">{{ $isRtl ? 'عدد الضيوف' : 'Guests' }}</span>
                        <div class="inline-flex items-center" style="gap: 12px;">
                            <button type="button" @click="guests = Math.max(1, guests - 1)"
                                    class="w-9 h-9 font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec]" style="border-radius: 999px;">−</button>
                            <span class="font-bold text-[#222] tabular-nums" style="min-width: 22px; text-align: center;" x-text="guests"></span>
                            <button type="button" @click="guests = Math.min(maxGuests, guests + 1)"
                                    class="w-9 h-9 font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec]" style="border-radius: 999px;">+</button>
                        </div>
                    </div>

                    <div x-show="quote" x-cloak class="flex items-center justify-between bg-[#fafafa]" style="margin-top: 12px; padding: 12px 14px; border-radius: 16px;">
                        <span class="text-[13px] text-[#717171]"><span x-text="nightsLabel()"></span> · {{ $isRtl ? 'شامل الضريبة' : 'VAT included' }}</span>
                        <span class="font-bold text-[#222] tabular-nums" dir="ltr">SR <span x-text="quote ? Number(quote.pricing.total).toLocaleString() : ''"></span></span>
                    </div>
                    <p x-show="quoteError" x-cloak class="text-[13px] text-[#dc2626]" style="margin-top: 10px;" x-text="quoteError"></p>
                </div>
            </div>
        </template>

        {{-- ══ STEP 2 · customer details + OTP ══ --}}
        <template x-if="step === 2">
            <div class="bg-white" style="border-radius: 24px; padding: 22px 18px; margin-top: 6px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.06);">
                <h2 class="font-bold text-[#222]" style="font-size: 18px;">{{ $isRtl ? 'بياناتك' : 'Your details' }}</h2>
                <p class="text-[13px] text-[#717171]" style="margin-top: 3px;">{{ $isRtl ? 'نتحقق من رقم جوالك برسالة نصية لإتمام الحجز.' : 'We verify your mobile number by SMS to complete the booking.' }}</p>

                <template x-if="!otpSent">
                    <div>
                        <input type="text" x-model="name" placeholder="{{ $isRtl ? 'الاسم' : 'Your name' }}"
                               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                               style="padding: 14px 16px; border-radius: 16px; margin-top: 14px;">
                        <input type="tel" x-model="phone" placeholder="5XXXXXXXX" dir="ltr" inputmode="numeric"
                               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                               style="padding: 14px 16px; border-radius: 16px; margin-top: 10px;">
                    </div>
                </template>

                <template x-if="otpSent">
                    <div>
                        <p class="text-[13px] text-[#717171]" style="margin-top: 14px;">
                            {{ $isRtl ? 'أدخل الرمز المرسل إلى' : 'Enter the code sent to' }} <span dir="ltr" class="font-bold" x-text="normPhone()"></span>
                        </p>
                        <input type="text" x-model="otp" maxlength="6" inputmode="numeric" autocomplete="one-time-code" dir="ltr"
                               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-center tracking-[8px] font-bold text-[20px] tabular-nums focus:outline-none"
                               style="padding: 14px 16px; border-radius: 16px; margin-top: 10px;">
                        <button type="button" @click="otpSent = false; otp = ''" class="w-full text-[13px] text-[#717171] hover:text-[#222]" style="margin-top: 12px;">
                            {{ $isRtl ? 'تغيير الرقم' : 'Change number' }}
                        </button>
                    </div>
                </template>

                <p x-show="authError" x-cloak class="text-[13px] text-[#dc2626]" style="margin-top: 10px;" x-text="authError"></p>
            </div>
        </template>

        {{-- ══ STEP 3 · summary ══ --}}
        <template x-if="step === 3">
            <div>
                <h2 class="font-bold text-[#222]" style="font-size: 18px; padding: 0 6px;">{{ $isRtl ? 'ملخص الحجز' : 'Booking summary' }}</h2>

                <div class="bg-white" style="border-radius: 24px; padding: 18px; margin-top: 12px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.06);">
                    <div class="flex items-center" style="gap: 12px;">
                        @if($place->coverPhoto?->url)
                            <img src="{{ $place->coverPhoto->url }}" alt="" class="object-cover shrink-0" style="width: 64px; height: 64px; border-radius: 16px;">
                        @endif
                        <div class="min-w-0">
                            <p class="font-bold text-[#222] truncate" style="font-size: 15px;">{{ $place->title }}</p>
                            <p class="text-[#717171] text-[12px]" style="margin-top: 2px;">
                                {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }}
                                @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
                            </p>
                        </div>
                    </div>

                    <div class="text-[14px] text-[#222] border-t border-[#f0f0f0]" style="margin-top: 14px; padding-top: 12px; line-height: 2.2;">
                        <div class="flex items-center justify-between">
                            <span class="text-[#717171]">{{ $isRtl ? 'الوصول' : 'Check-in' }}</span>
                            <span class="font-semibold tabular-nums" dir="ltr" x-text="checkIn"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[#717171]">{{ $isRtl ? 'المغادرة' : 'Checkout' }}</span>
                            <span class="font-semibold tabular-nums" dir="ltr" x-text="checkOut"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[#717171]">{{ $isRtl ? 'المدة' : 'Stay' }}</span>
                            <span class="font-semibold" x-text="nightsLabel()"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[#717171]">{{ $isRtl ? 'الضيوف' : 'Guests' }}</span>
                            <span class="font-semibold tabular-nums" x-text="guests"></span>
                        </div>
                    </div>

                    <div class="text-[14px] border-t border-[#f0f0f0]" style="margin-top: 10px; padding-top: 12px; line-height: 2.2;">
                        <div class="flex items-center justify-between">
                            <span class="text-[#717171]">{{ $isRtl ? 'الإقامة' : 'Stay subtotal' }}</span>
                            <span class="tabular-nums" dir="ltr">SR <span x-text="quote ? Number(quote.pricing.subtotal).toLocaleString() : ''"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[#717171]">{{ $isRtl ? 'ضريبة القيمة المضافة' : 'VAT' }}</span>
                            <span class="tabular-nums" dir="ltr">SR <span x-text="quote ? Number(quote.pricing.vat).toLocaleString() : ''"></span></span>
                        </div>
                        <div class="flex items-center justify-between font-bold text-[#222]" style="font-size: 16px;">
                            <span>{{ $isRtl ? 'الإجمالي' : 'Total' }}</span>
                            <span class="tabular-nums" dir="ltr">SR <span x-text="quote ? Number(quote.pricing.total).toLocaleString() : ''"></span></span>
                        </div>
                    </div>
                </div>

                <p class="text-[12px] text-[#999]" style="margin-top: 12px; padding: 0 6px; line-height: 1.8;">
                    {{ $isRtl ? 'بالضغط على "ادفع الآن" ستنتقل لصفحة الدفع الآمنة. يتأكد حجزك فور إتمام الدفع.' : 'Tapping "Pay now" opens the secure payment page. Your booking confirms the moment payment completes.' }}
                </p>
                <p x-show="submitError" x-cloak class="text-[13px] text-[#dc2626] bg-[#fef2f2]" style="margin-top: 10px; padding: 12px 14px; border-radius: 14px;" x-text="submitError"></p>
            </div>
        </template>
    </main>

    {{-- ── Sticky action bar (contextual per step) ── --}}
    <div class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur border-t border-[#ebebeb]" style="padding: 12px 18px calc(12px + env(safe-area-inset-bottom));">
        <div class="mx-auto flex items-center" style="max-width: 560px; gap: 10px;">
            <button type="button" x-show="step > 1" x-cloak @click="back()"
                    class="font-semibold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec] shrink-0"
                    style="padding: 15px 20px; border-radius: 16px;">{{ $isRtl ? 'السابق' : 'Back' }}</button>

            {{-- Step 1: Next --}}
            <button type="button" x-show="step === 1" @click="goDetails()" :disabled="!quote"
                    class="flex-1 font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.99] transition-all"
                    style="padding: 15px; border-radius: 16px; font-size: 16px;">
                <span x-text="quote ? '{{ $isRtl ? 'التالي' : 'Next' }} · SR ' + Number(quote.pricing.total).toLocaleString() : '{{ $isRtl ? 'اختر التواريخ أولاً' : 'Pick your dates first' }}'"></span>
            </button>

            {{-- Step 2: Next = send OTP, then Verify — both live in the bottom bar --}}
            <button type="button" x-show="step === 2 && !otpSent" x-cloak @click="requestOtp()"
                    :disabled="authBusy || !name.trim() || normPhone().length !== 9"
                    class="flex-1 font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.99] transition-all"
                    style="padding: 15px; border-radius: 16px; font-size: 16px;">
                <span x-show="!authBusy">{{ $isRtl ? 'التالي' : 'Next' }}</span>
                <span x-show="authBusy" x-cloak>{{ $isRtl ? 'جارٍ إرسال الرمز…' : 'Sending the code…' }}</span>
            </button>
            <button type="button" x-show="step === 2 && otpSent" x-cloak @click="verifyOtp()"
                    :disabled="authBusy || otp.length < 4"
                    class="flex-1 font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.99] transition-all"
                    style="padding: 15px; border-radius: 16px; font-size: 16px;">
                <span x-show="!authBusy">{{ $isRtl ? 'التالي' : 'Next' }}</span>
                <span x-show="authBusy" x-cloak>{{ $isRtl ? 'جارٍ التحقق…' : 'Verifying…' }}</span>
            </button>

            {{-- Step 3: Pay --}}
            <button type="button" x-show="step === 3" x-cloak @click="submit()" :disabled="submitting"
                    class="flex-1 font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:bg-[#dddddd] active:scale-[0.99] transition-all"
                    style="padding: 15px; border-radius: 16px; font-size: 16px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                <span x-show="!submitting">{{ $isRtl ? 'ادفع الآن' : 'Pay now' }}</span>
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
        isRtl: init.isRtl,

        step: 1,
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

        // ── step navigation ──
        goDetails() {
            if (!this.quote) return;
            this.step = this.authed ? 3 : 2;
            window.scrollTo({ top: 0 });
        },
        back() {
            this.step = this.step === 3 && !this.authed ? 2 : 1;
            window.scrollTo({ top: 0 });
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
            if (this.checkIn && this.checkOut && cell.date > this.checkIn && cell.date < this.checkOut) return 'bg-[#fde5e2] text-[#222] font-semibold';
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
            return (this.phone || '').replace(/\D+/g, '').replace(/^(?:00966|966)/, '').replace(/^0+/, '');
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
                if (!res.ok) { this.authError = this.isRtl ? 'رمز غير صحيح أو منتهي.' : 'Wrong or expired code.'; return; }
                // JWT cookie is set by the response; the booking POST rides on it.
                this.authed = true;
                this.step = 3;
                window.scrollTo({ top: 0 });
            } catch (e) { this.authError = this.isRtl ? 'تعذر التحقق.' : 'Verification failed.'; }
            finally { this.authBusy = false; }
        },

        // ── submit ──
        async submit() {
            if (this.submitting) return;
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
