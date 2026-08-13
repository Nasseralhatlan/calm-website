@extends('layouts.app')

@php
    use Illuminate\Support\Carbon;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $city = $place->cityArea?->city;
    $viewer = auth('api')->user();
    $fmtTime = fn (?string $t) => $t ? Carbon::parse($t)->format('g:i A') : '—';
    $init = [
        'placeId' => $place->id,
        'maxGuests' => (int) ($place->max_guests ?? 1),
        'authed' => $viewer !== null,
        'submitUrl' => route('book.store', $place),
        'isRtl' => $isRtl,
        'checkInTime' => $fmtTime($place->check_in_time),
        'checkOutTime' => $fmtTime($place->check_out_time),
        'checkoutNextDay' => (bool) $place->checkout_next_day,
    ];
@endphp

@section('title', ($place->title ?: 'Calm').' · '.($isRtl ? 'احجز الآن' : 'Book now'))

@section('body')
<div x-data="bookingFunnel(@js($init))" x-init="load()" dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
     class="min-h-screen {{ $fa }}" style="background-color: #fff;">

    {{-- ── Calm header ── --}}
    <header class="w-full bg-white sticky top-0 z-30 backdrop-blur" style="background-color: rgba(255,255,255,0.95);">
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

    <main class="mx-auto w-full" style="max-width: 560px; padding: 0 0 190px;">

        {{-- ══ STEP 1 · place + scrolling date picker (app-style) ══ --}}
        <div x-show="step === 1">
            <div style="padding: 18px 18px 0;">
                @if($place->coverPhoto?->url)
                    <img src="{{ $place->coverPhoto->url }}" alt=""
                         class="w-full object-cover" style="height: 200px; border-radius: 24px;">
                @endif
                <div style="padding: 14px 4px 0;">
                    <h1 class="font-bold text-[#222]" style="font-size: 21px;">{{ $place->title }}</h1>
                    <p class="text-[#717171]" style="font-size: 14px; margin-top: 3px;">
                        {{ $place->type?->icon }} {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }}
                        @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
                        · <span class="font-bold text-[#222]" dir="ltr">SR {{ number_format($place->price) }}</span> <span class="text-[13px]">{{ $isRtl ? '/ الليلة' : '/ night' }}</span>
                    </p>
                </div>
            </div>

            {{-- Selection header: nights count + range (Hijri subtitle, like the app) --}}
            <div class="text-center" style="padding: 20px 18px 0;">
                <p class="font-bold text-[#222]" style="font-size: 20px;" x-text="rangeTitle()"></p>
                <p class="text-[#717171] text-[13px]" style="margin-top: 2px; min-height: 20px;" x-text="rangeSubtitle()"></p>
            </div>

            {{-- Weekday letters --}}
            <div style="padding: 14px 18px 0;">
                <div class="grid grid-cols-7 text-center" style="font-size: 12px; color: #b8b8b8; font-weight: 500;">
                    <template x-for="d in dayNames()"><span x-text="d"></span></template>
                </div>
            </div>

            {{-- Scrolling months --}}
            <div style="padding: 4px 18px 0;">
                <template x-for="month in months" :key="month.key">
                    <div style="margin-top: 18px;">
                        <p class="font-bold text-[#222]" style="font-size: 18px; padding: 0 4px;" x-text="month.label"></p>
                        <div class="grid grid-cols-7" style="row-gap: 8px; margin-top: 10px;">
                            <template x-for="cell in month.cells" :key="cell.key">
                                {{-- All visual state is inline (one binding per element):
                                     the wrapper paints the connected range band, the button
                                     is the circular day indicator. --}}
                                <div class="relative flex items-center justify-center" :style="cellStyle(cell)">
                                    <button type="button"
                                            x-text="cell.day || ''"
                                            :disabled="!cell.day || cell.disabled"
                                            @click="pickDay(cell.date)"
                                            class="relative tabular-nums select-none flex items-center justify-center transition-colors"
                                            :style="dayStyle(cell)"></button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <p x-show="quoteError" x-cloak class="text-[13px] text-[#dc2626] text-center" style="margin-top: 14px; padding: 0 18px;" x-text="quoteError"></p>

            {{-- Guests --}}
            <div class="flex items-center justify-between" style="margin: 24px 18px 0;">
                <span class="text-[15px] font-semibold text-[#222]">{{ $isRtl ? 'عدد الضيوف' : 'Guests' }}</span>
                <div class="inline-flex items-center" style="gap: 14px;">
                    <button type="button" @click="guests = Math.max(1, guests - 1)"
                            class="w-10 h-10 font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec]" style="border-radius: 999px;">−</button>
                    <span class="font-bold text-[#222] tabular-nums" style="min-width: 22px; text-align: center; font-size: 16px;" x-text="guests"></span>
                    <button type="button" @click="guests = Math.min(maxGuests, guests + 1)"
                            class="w-10 h-10 font-bold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec]" style="border-radius: 999px;">+</button>
                </div>
            </div>
        </div>

        {{-- ══ STEP 2 · customer details + OTP ══ --}}
        <div x-show="step === 2" x-cloak style="padding: 22px 18px 0;">
            <h2 class="font-bold text-[#222] text-center" style="font-size: 19px;">{{ $isRtl ? 'بياناتك' : 'Your details' }}</h2>
            <p class="text-[13px] text-[#717171] text-center" style="margin-top: 4px;">{{ $isRtl ? 'نتحقق من رقم جوالك برسالة نصية لإتمام الحجز.' : 'We verify your mobile number by SMS to complete the booking.' }}</p>

            <template x-if="!otpSent">
                <div style="margin-top: 18px;">
                    <input type="text" x-model="name" placeholder="{{ $isRtl ? 'الاسم' : 'Your name' }}"
                           class="w-full text-[15px] focus:outline-none"
                           style="background-color: #f5f5f6; padding: 15px 16px; border-radius: 16px;">
                    {{-- Dial-code picker + phone, LTR so the prefix never flips
                         (same pattern as the login page, funnel styling). --}}
                    <div class="flex items-center" dir="ltr"
                         style="background-color: #f5f5f6; border-radius: 16px; margin-top: 10px;">
                        <select x-model="countryCode" aria-label="Country dial code"
                                class="bg-transparent text-[15px] font-semibold text-[#222] tabular-nums shrink-0 focus:outline-none cursor-pointer"
                                style="appearance: none; -webkit-appearance: none; -moz-appearance: none;
                                       padding: 15px 26px 15px 14px;
                                       border-right: 1px solid #e6e6e8;
                                       background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%2210%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23222%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22/></svg>');
                                       background-repeat: no-repeat;
                                       background-position: right 9px center;">
                            @foreach($countries as $country)
                                <option value="{{ $country->country_code }}">
                                    {{ $country->avatar ? $country->avatar.'  ' : '' }}{{ $country->dial_code }}
                                </option>
                            @endforeach
                        </select>
                        <input type="tel" x-model="phone" placeholder="5XXXXXXXX" inputmode="numeric" maxlength="16"
                               autocomplete="tel-national"
                               class="flex-1 text-[15px] tabular-nums focus:outline-none bg-transparent"
                               style="padding: 15px 16px; min-width: 0; letter-spacing: 0.5px;">
                    </div>
                </div>
            </template>

            <template x-if="otpSent">
                <div style="margin-top: 18px;">
                    <p class="text-[13px] text-[#717171] text-center">
                        {{ $isRtl ? 'أدخل الرمز المرسل إلى' : 'Enter the code sent to' }} <span dir="ltr" class="font-bold" x-text="normPhone()"></span>
                    </p>
                    <input type="text" x-model="otp" maxlength="6" inputmode="numeric" autocomplete="one-time-code" dir="ltr"
                           class="w-full text-center tracking-[8px] font-bold text-[22px] tabular-nums focus:outline-none"
                           style="background-color: #f5f5f6; padding: 15px 16px; border-radius: 16px; margin-top: 12px;">

                    {{-- Resend countdown --}}
                    <div class="text-center text-[13px]" style="margin-top: 14px;">
                        <span x-show="resendIn > 0" class="text-[#999] tabular-nums">
                            {{ $isRtl ? 'يمكنك إعادة الإرسال بعد' : 'You can resend in' }} <span class="font-bold" x-text="resendIn"></span> {{ $isRtl ? 'ثانية' : 's' }}
                        </span>
                        <button type="button" x-show="resendIn === 0" x-cloak @click="requestOtp()"
                                class="font-bold text-[#222] underline">{{ $isRtl ? 'إعادة إرسال الرمز' : 'Resend the code' }}</button>
                    </div>

                    <button type="button" @click="otpSent = false; otp = ''; stopResendTimer()" class="w-full text-[13px] text-[#717171] hover:text-[#222]" style="margin-top: 12px;">
                        {{ $isRtl ? 'تغيير الرقم' : 'Change number' }}
                    </button>
                </div>
            </template>

            <p x-show="authError" x-cloak class="text-[13px] text-[#dc2626] text-center" style="margin-top: 12px;" x-text="authError"></p>
        </div>

        {{-- ══ STEP 3 · confirm & pay (app-style summary) ══ --}}
        <div x-show="step === 3" x-cloak style="padding: 22px 18px 0;">
            <h2 class="font-bold text-[#222] text-center" style="font-size: 19px;">{{ $isRtl ? 'تأكيد و دفع' : 'Confirm & pay' }}</h2>

            <div class="bg-white" style="border-radius: 24px; padding: 18px; margin-top: 16px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                {{-- Place row --}}
                <div class="flex items-center" style="gap: 12px;">
                    @if($place->coverPhoto?->url)
                        <img src="{{ $place->coverPhoto->url }}" alt="" class="object-cover shrink-0" style="width: 74px; height: 74px; border-radius: 18px;">
                    @endif
                    <div class="min-w-0">
                        <p class="font-bold text-[#222] truncate" style="font-size: 17px;">{{ $place->title }}</p>
                        <p class="text-[#717171] text-[13px]" style="margin-top: 3px;">
                            {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }}
                            @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
                        </p>
                    </div>
                </div>

                {{-- Arrival / departure --}}
                <div style="margin-top: 20px;">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-bold text-[#222] text-[15px]">{{ $isRtl ? 'الوصول' : 'Arrival' }}</p>
                            <p class="text-[#717171] text-[14px]" style="margin-top: 2px;">
                                <span x-text="fmtDay(checkIn)"></span> · <span dir="ltr">{{ $fmtTime($place->check_in_time) }}</span>
                            </p>
                        </div>
                        <button type="button" @click="step = 1; window.scrollTo({top: 0})"
                                class="text-[13px] font-semibold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec] shrink-0"
                                style="padding: 8px 16px; border-radius: 12px;">{{ $isRtl ? 'تغيير' : 'Change' }}</button>
                    </div>
                    <div style="margin-top: 14px;">
                        <p class="font-bold text-[#222] text-[15px]">{{ $isRtl ? 'المغادرة' : 'Departure' }}</p>
                        <p class="text-[#717171] text-[14px]" style="margin-top: 2px;">
                            <span x-text="fmtDay(checkoutDay())"></span> · <span dir="ltr">{{ $fmtTime($place->check_out_time) }}</span>
                            @if($place->checkout_next_day)<span> ({{ $isRtl ? 'اليوم التالي' : 'next day' }})</span>@endif
                        </p>
                    </div>
                </div>

                {{-- Money --}}
                <div class="text-[14px]" style="margin-top: 20px; line-height: 2.4;">
                    <div class="flex items-center justify-between">
                        <span class="text-[#717171]">{{ $isRtl ? 'الإقامة' : 'Stay' }} · <span x-text="nightsLabel()"></span></span>
                        <span class="font-bold text-[#222] tabular-nums" dir="ltr"><span x-text="fmtMoney(quote?.pricing.subtotal)"></span> SR</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#717171]">{{ $isRtl ? 'ضريبة القيمة المضافة (15%)' : 'VAT (15%)' }}</span>
                        <span class="font-bold text-[#222] tabular-nums" dir="ltr"><span x-text="fmtMoney(quote?.pricing.vat)"></span> SR</span>
                    </div>
                    <div class="flex items-center justify-between" style="margin-top: 8px;">
                        <span class="font-bold text-[#222]" style="font-size: 16px;">{{ $isRtl ? 'الإجمالي' : 'Total' }}</span>
                        <span class="font-bold text-[#222] tabular-nums" style="font-size: 17px;" dir="ltr"><span x-text="fmtMoney(quote?.pricing.total)"></span> SR</span>
                    </div>
                </div>
            </div>

            <p x-show="submitError" x-cloak class="text-[13px] text-[#dc2626] bg-[#fef2f2]" style="margin-top: 12px; padding: 12px 14px; border-radius: 14px;" x-text="submitError"></p>
        </div>
    </main>

    {{-- ── Sticky action bar ── --}}
    <div class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur" style="padding: 10px 18px calc(14px + env(safe-area-inset-bottom)); box-shadow: 0 -8px 24px rgba(0,0,0,0.05);">
        <div class="mx-auto" style="max-width: 560px;">

            <div class="flex items-center" style="gap: 10px;">
                <button type="button" x-show="step > 1" x-cloak @click="back()"
                        class="font-semibold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec] shrink-0"
                        style="padding: 16px 20px; border-radius: 999px;">{{ $isRtl ? 'السابق' : 'Back' }}</button>

                {{-- Step 1: Next --}}
                <button type="button" x-show="step === 1" @click="goDetails()" :disabled="!quote"
                        class="flex-1 font-bold text-white bg-[#222] hover:bg-black disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.99] transition-all"
                        style="padding: 17px; border-radius: 18px; font-size: 16px;">
                    <span x-text="quote ? '{{ $isRtl ? 'التالي' : 'Next' }}' : '{{ $isRtl ? 'اختر التواريخ' : 'Pick your dates' }}'"></span>
                </button>

                {{-- Step 2: Next = send OTP / verify --}}
                <button type="button" x-show="step === 2 && !otpSent" x-cloak @click="requestOtp()"
                        :disabled="authBusy || !name.trim() || normPhone().length !== 9"
                        class="flex-1 font-bold text-white bg-[#222] hover:bg-black disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.99] transition-all"
                        style="padding: 17px; border-radius: 18px; font-size: 16px;">
                    <svg x-show="authBusy" x-cloak class="calm-spinner inline-block align-middle" style="margin-inline-end: 8px;" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="12" cy="12" r="10" stroke-opacity="0.3"/><path d="M22 12a10 10 0 0 1-10 10"/></svg>
                    <span x-show="!authBusy">{{ $isRtl ? 'التالي' : 'Next' }}</span>
                    <span x-show="authBusy" x-cloak>{{ $isRtl ? 'جارٍ إرسال الرمز…' : 'Sending the code…' }}</span>
                </button>
                <button type="button" x-show="step === 2 && otpSent" x-cloak @click="verifyOtp()"
                        :disabled="authBusy || otp.length < 4"
                        class="flex-1 font-bold text-white bg-[#222] hover:bg-black disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.99] transition-all"
                        style="padding: 17px; border-radius: 18px; font-size: 16px;">
                    <svg x-show="authBusy" x-cloak class="calm-spinner inline-block align-middle" style="margin-inline-end: 8px;" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="12" cy="12" r="10" stroke-opacity="0.3"/><path d="M22 12a10 10 0 0 1-10 10"/></svg>
                    <span x-show="!authBusy">{{ $isRtl ? 'التالي' : 'Next' }}</span>
                    <span x-show="authBusy" x-cloak>{{ $isRtl ? 'جارٍ التحقق…' : 'Verifying…' }}</span>
                </button>

                {{-- Step 3: Pay --}}
                <button type="button" x-show="step === 3" x-cloak @click="submit()" :disabled="submitting"
                        class="flex-1 font-bold text-white bg-[#222] hover:bg-black disabled:bg-[#dddddd] active:scale-[0.99] transition-all"
                        style="padding: 17px; border-radius: 18px; font-size: 16px;">
                    <svg x-show="submitting" x-cloak class="calm-spinner inline-block align-middle" style="margin-inline-end: 8px;" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="12" cy="12" r="10" stroke-opacity="0.3"/><path d="M22 12a10 10 0 0 1-10 10"/></svg>
                    <span x-show="!submitting">{{ $isRtl ? 'متابعة للدفع' : 'Continue to payment' }}</span>
                    <span x-show="submitting" x-cloak>{{ $isRtl ? 'جارٍ التحويل للدفع…' : 'Heading to payment…' }}</span>
                </button>
            </div>

            <p x-show="step === 3" x-cloak class="text-center text-[12px] text-[#999]" style="margin-top: 8px;">
                {{ $isRtl ? 'بالضغط، أوافق على' : 'By tapping, I agree to the' }}
                <a href="{{ route('pages.terms') }}" target="_blank" class="underline">{{ $isRtl ? 'شروط الحجز' : 'booking terms' }}</a>.
            </p>
        </div>
    </div>
</div>

<script>
function bookingFunnel(init) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const AR_DAYS = ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'];
    const EN_DAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    // Parse 'YYYY-MM-DD' as a LOCAL date — new Date(str) is UTC midnight and
    // shifts a day for viewers west of UTC.
    const parseD = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const todayIso = iso(new Date());
    const MONTHS_AHEAD = 12; // a full year of dates ahead

    const gregFmt = new Intl.DateTimeFormat(init.isRtl ? 'ar' : 'en', { weekday: 'long', day: 'numeric', month: 'long' });
    let hijriFmt = null;
    try { hijriFmt = new Intl.DateTimeFormat(init.isRtl ? 'ar-SA-u-ca-islamic-umalqura' : 'en-u-ca-islamic-umalqura', { weekday: 'short', day: 'numeric', month: 'long' }); } catch (e) {}

    return {
        placeId: init.placeId,
        maxGuests: init.maxGuests || 1,
        authed: init.authed,
        isRtl: init.isRtl,

        step: 1,
        months: [],
        unavailable: new Set(),
        checkIn: null,
        checkOut: null,
        guests: 1,
        quote: null,
        quoteError: '',
        quoteSeq: 0,

        name: '', phone: '', otp: '', countryCode: 'SA',
        otpSent: false, authBusy: false, authError: '',
        resendIn: 0, resendTimer: null,
        submitting: false, submitError: '',

        async load() {
            this.buildMonths();
            try {
                const res = await fetch(`/api/places/${this.placeId}/unavailable-dates`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                (json.data?.unavailable_dates || []).forEach((d) => this.unavailable.add(d));
                this.buildMonths(); // re-render with strikethroughs
            } catch (e) { /* calendar still works; server re-validates */ }
            this.$watch('guests', () => this.fetchQuote());
        },

        // ── calendar (vertical month list, app-style) ──
        dayNames() { return this.isRtl ? AR_DAYS : EN_DAYS; },
        buildMonths() {
            const list = [];
            const now = new Date();
            const AR_MONTHS = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
            const EN_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            for (let i = 0; i < MONTHS_AHEAD; i++) {
                const y = now.getFullYear(), mIdx = now.getMonth() + i;
                const first = new Date(y, mIdx, 1);
                const cells = [];
                for (let p = 0; p < first.getDay(); p++) cells.push({ key: `p${p}`, day: null });
                const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
                for (let d = 1; d <= days; d++) {
                    const date = iso(new Date(first.getFullYear(), first.getMonth(), d));
                    cells.push({ key: date, day: d, date, disabled: date < todayIso || this.unavailable.has(date) });
                }
                list.push({
                    key: `${first.getFullYear()}-${first.getMonth()}`,
                    label: `${(this.isRtl ? AR_MONTHS : EN_MONTHS)[first.getMonth()]} ${first.getFullYear()}`,
                    cells,
                });
            }
            this.months = list;
        },
        pickDay(date) {
            this.submitError = '';
            this.quoteError = '';
            const singleSelected = this.checkIn !== null && this.checkOut === this.checkIn;

            // A one-day pick extends into a range by tapping a LATER day.
            if (singleSelected && date > this.checkIn) {
                if (!this.rangeFree(this.checkIn, date)) {
                    this.quoteError = this.isRtl ? 'يوجد أيام محجوزة ضمن المدى المختار — اختر تواريخ أخرى.' : 'Some days in that range are booked — pick different dates.';
                    this.checkIn = date; this.checkOut = date;
                } else {
                    this.checkOut = date;
                }
                this.fetchQuote();
                return;
            }

            if (singleSelected && date === this.checkIn) return; // already picked

            // Anything else (first pick, an earlier day, or a full range
            // already selected): start fresh as a ONE-DAY stay — the Next
            // button lights up from the very first tap.
            this.checkIn = date;
            this.checkOut = date;
            this.fetchQuote();
        },
        rangeFree(a, b) {
            const cur = parseD(a); const end = parseD(b);
            while (cur < end) { if (this.unavailable.has(iso(cur))) return false; cur.setDate(cur.getDate() + 1); }
            return true;
        },
        hasRange() {
            return !!(this.checkIn && this.checkOut);
        },
        cellStyle(cell) {
            // The wrapper paints the connected band: full grey for in-between
            // days, half grey (inward side only) under the endpoint circles.
            // Physical sides are computed from the layout direction.
            let css = 'height: 46px;';
            if (!cell.date || !this.hasRange()) return css;
            const BAND = 'rgba(0,0,0,0.05)';
            const s = cell.date === this.checkIn, e = cell.date === this.checkOut;
            const between = cell.date > this.checkIn && cell.date < this.checkOut;
            // "toward checkout" = left in RTL, right in LTR.
            const inward = this.isRtl ? 'left' : 'right';
            const outward = this.isRtl ? 'right' : 'left';
            if (between) css += `background-color: ${BAND};`;
            else if (s && !e) css += `background: linear-gradient(to ${inward}, transparent 50%, ${BAND} 50%);`;
            else if (e && !s) css += `background: linear-gradient(to ${outward}, transparent 50%, ${BAND} 50%);`;
            return css;
        },
        dayStyle(cell) {
            let css = 'width: 40px; height: 40px; border-radius: 999px; font-size: 15px;';
            if (!cell.day) return css;
            if (cell.disabled) return css + 'color: #cfcfcf; text-decoration: line-through; cursor: default;';
            if (cell.date === this.checkIn || cell.date === this.checkOut) return css + 'background-color: #222; color: #fff; font-weight: 700;';
            if (this.hasRange() && cell.date > this.checkIn && cell.date < this.checkOut) return css + 'color: #222; font-weight: 600;';
            return css + 'color: #444;';
        },

        // ── selection header + quick chips ──
        rangeTitle() {
            if (!this.checkIn || !this.checkOut) return this.isRtl ? 'اختر التواريخ' : 'Pick your dates';
            const n = this.nights();
            if (!this.isRtl) return n === 1 ? '1 day' : `${n} days`;
            return n === 1 ? 'يوم واحد' : (n === 2 ? 'يومان' : `${n} أيام`);
        },
        rangeSubtitle() {
            if (!this.checkIn || !this.checkOut) return '';
            if (this.checkIn === this.checkOut) {
                const d = hijriFmt ? (() => { try { return hijriFmt.format(parseD(this.checkIn)); } catch (e) { return this.checkIn; } })() : this.checkIn;
                return d + (this.isRtl ? ' — اضغط يوماً لاحقاً للتمديد' : ' — tap a later day to extend');
            }
            if (!hijriFmt) return '';
            try { return `${hijriFmt.format(parseD(this.checkIn))} – ${hijriFmt.format(parseD(this.checkOut))}`; } catch (e) { return ''; }
        },
        nights() {
            // Inclusive DAY count (app semantics: Jul 26 → Jul 31 = 6 أيام).
            return Math.round((parseD(this.checkOut) - parseD(this.checkIn)) / 86400000) + 1;
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
            if (!this.isRtl) return n === 1 ? '1 day' : `${n} days`;
            return n === 1 ? 'يوم واحد' : (n === 2 ? 'يومان' : `${n} أيام`);
        },
        fmtMoney(v) { return v == null ? '' : Number(v).toLocaleString(); },
        fmtDay(dateStr) {
            if (!dateStr) return '';
            try { return gregFmt.format(parseD(dateStr)); } catch (e) { return dateStr; }
        },
        checkoutDay() {
            if (!this.checkOut) return null;
            if (!init.checkoutNextDay) return this.checkOut;
            const d = parseD(this.checkOut);
            return iso(d);
        },

        // ── inline OTP auth (existing API endpoints; verify sets the JWT cookie) ──
        normPhone() {
            return (this.phone || '').replace(/\D+/g, '').replace(/^(?:00966|966)/, '').replace(/^0+/, '');
        },
        startResendTimer() {
            this.stopResendTimer();
            this.resendIn = 60;
            this.resendTimer = setInterval(() => {
                if (this.resendIn > 0) this.resendIn--;
                if (this.resendIn === 0) this.stopResendTimer();
            }, 1000);
        },
        stopResendTimer() {
            if (this.resendTimer) { clearInterval(this.resendTimer); this.resendTimer = null; }
            this.resendIn = 0;
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
                this.startResendTimer();
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
                this.stopResendTimer();
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
