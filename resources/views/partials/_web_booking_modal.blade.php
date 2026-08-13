{{-- Booking modal (place page «احجز الآن»): dates → summary → pay/login.
     Opens on the `calm-open-booking` window event. If the visitor arrived
     from search with ?check_in/?check_out (or resumes after logging in),
     the dates step is skipped and the modal opens on the summary.
     Same rails as /book/{place}: quote API + POST book.store → Moyasar. --}}
@php
    $bmFmtTime = fn (?string $t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('g:i A') : '—';
@endphp
<div x-data="calmBookingModal(@js([
        'placeId' => $place->id,
        'maxGuests' => (int) ($place->max_guests ?: 1),
        'checkoutNextDay' => (bool) $place->checkout_next_day,
        'authed' => auth('api')->check(),
        'isRtl' => $isRtl,
        'submitUrl' => route('book.store', $place),
    ]))"
     @calm-open-booking.window="openBooking()"
     @keydown.escape.window="closeBooking()">
    <div x-show="open" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.5);" @click="closeBooking()" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:max-w-2xl sm:h-fit bg-white flex flex-col overflow-hidden"
             style="border-radius: 28px 28px 0 0; corner-shape: squircle; max-height: calc(100% - 56px); height: calc(100% - 56px);"
             x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

            {{-- Header — filters-style X + centred title; back arrow on the summary --}}
            <div class="relative flex items-center justify-center shrink-0" style="padding: 16px 20px 12px;">
                <button type="button" @click="closeBooking()" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                        class="calm-press calm-round absolute flex items-center justify-center bg-white text-black"
                        style="inset-inline-start: 16px; width: 42px; height: 42px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 16px;">✕</button>
                <h2 class="font-bold text-black {{ $fa }}" style="font-size: 18px;"
                    x-text="step === 'dates' ? '{{ $isRtl ? 'اختر التواريخ' : 'Pick your dates' }}' : '{{ $isRtl ? 'ملخص الحجز' : 'Booking summary' }}'"></h2>
            </div>

            {{-- ── DATES ── --}}
            <div x-show="step === 'dates'" class="flex-1 min-h-0 flex flex-col">
                <div class="text-center shrink-0" style="padding: 2px 20px 10px;">
                    <div class="font-bold text-black {{ $fa }}" style="font-size: 15px;" x-text="rangeTitle()"></div>
                    <p x-show="quoteError" x-cloak class="{{ $fa }}" style="font-size: 12.5px; color: #dc2626; margin-top: 4px;" x-text="quoteError"></p>
                </div>
                {{-- Day-of-week header --}}
                <div class="grid grid-cols-7 text-center shrink-0" style="padding: 0 20px 6px; border-bottom: 1px solid #F1F1F1;">
                    <template x-for="(d, i) in dayNames()" :key="'dn' + i">
                        <span style="font-size: 12px; color: #AAAAAA;" x-text="d"></span>
                    </template>
                </div>
                <div class="flex-1 overflow-y-auto" style="padding: 6px 20px 20px; -webkit-overflow-scrolling: touch; overscroll-behavior: contain;">
                    <template x-for="m in months" :key="m.key">
                        <div style="padding-top: 18px;">
                            <div class="text-center font-bold text-black {{ $fa }}" style="font-size: 15px; margin-bottom: 8px;" x-text="m.label"></div>
                            <div class="grid grid-cols-7">
                                <template x-for="cell in m.cells" :key="m.key + '-' + cell.key">
                                    <div class="flex items-center justify-center" :style="cellStyle(cell)">
                                        <button type="button" x-show="cell.day" :disabled="cell.disabled"
                                                @click="pickDay(cell.date)"
                                                class="flex items-center justify-center tabular-nums"
                                                :style="dayStyle(cell)" x-text="cell.day"></button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="shrink-0" style="padding: 12px 20px calc(16px + env(safe-area-inset-bottom)); border-top: 1px solid #F1F1F1;">
                    <button type="button" @click="toSummary()" :disabled="!quote"
                            class="calm-press w-full font-bold text-white {{ $fa }}"
                            :style="'padding: 16px; border-radius: 18px; font-size: 16px; transition: opacity 0.2s; background-color: #1A1A1A; opacity: ' + (quote ? 1 : 0.35) + ';'">
                        <span x-text="quote ? '{{ $isRtl ? 'التالي' : 'Next' }}' : '{{ $isRtl ? 'اختر التواريخ' : 'Pick your dates' }}'"></span>
                    </button>
                </div>
            </div>

            {{-- ── SUMMARY ── --}}
            <div x-show="step === 'summary'" x-cloak class="flex-1 min-h-0 flex flex-col">
                <div class="flex-1 overflow-y-auto {{ $start }}" style="padding: 8px 20px 20px; -webkit-overflow-scrolling: touch; overscroll-behavior: contain;">
                    {{-- Place row --}}
                    <div class="flex items-center" style="gap: 14px;">
                        @php $bmCover = $place->coverPhoto?->url ?? $place->photos->first()?->url; @endphp
                        <span class="shrink-0 overflow-hidden" style="width: 74px; height: 74px; border-radius: 20px; corner-shape: squircle; -webkit-corner-shape: squircle; background-color: #F3F4F6;">
                            @if($bmCover)<img src="{{ $bmCover }}" alt="" class="w-full h-full object-cover">@endif
                        </span>
                        <div class="min-w-0">
                            <div class="font-bold text-black truncate {{ $fa }}" style="font-size: 16px;">{{ $place->localized_title }}</div>
                            <div class="truncate {{ $fa }}" style="font-size: 13px; color: #AAAAAA; margin-top: 3px;">
                                {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }} · {{ $isRtl ? $place->cityArea?->city?->name_ar : $place->cityArea?->city?->name_en }}
                            </div>
                        </div>
                    </div>

                    {{-- Dates card --}}
                    <div class="bg-white" style="margin-top: 18px; border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 30px rgba(0,0,0,0.05); padding: 4px 18px;">
                        <div class="flex items-center justify-between" style="padding: 14px 0; border-bottom: 1px solid #F5F5F5; gap: 12px;">
                            <span class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'الدخول' : 'Check-in' }}</span>
                            <span class="font-bold text-black {{ $fa }}" style="font-size: 13.5px;"><span x-text="fmtDay(checkIn)"></span> · <bdi dir="ltr">{{ $bmFmtTime($place->check_in_time) }}</bdi></span>
                        </div>
                        <div class="flex items-center justify-between" style="padding: 14px 0; border-bottom: 1px solid #F5F5F5; gap: 12px;">
                            <span class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'المغادرة' : 'Check-out' }}</span>
                            <span class="font-bold text-black {{ $fa }}" style="font-size: 13.5px;"><span x-text="fmtDay(checkoutDay())"></span> · <bdi dir="ltr">{{ $bmFmtTime($place->check_out_time) }}</bdi></span>
                        </div>
                        <div class="flex items-center justify-between" style="padding: 14px 0;">
                            <span class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'مدة الإقامة' : 'Stay' }}</span>
                            <button type="button" @click="step = 'dates'" class="font-bold text-black underline {{ $fa }}" style="font-size: 13px;">
                                <span x-text="nightsLabel() || rangeTitle()"></span> — {{ $isRtl ? 'تغيير' : 'change' }}
                            </button>
                        </div>
                    </div>

                    {{-- Guests stepper --}}
                    <div class="flex items-center justify-between" style="margin-top: 20px;">
                        <div>
                            <div class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'الضيوف' : 'Guests' }}</div>
                            <div class="{{ $fa }}" style="font-size: 12.5px; color: #AAAAAA; margin-top: 2px;">{{ $isRtl ? "بحد أقصى {$place->max_guests} ضيوف" : "Up to {$place->max_guests} guests" }}</div>
                        </div>
                        <div class="flex items-center" style="gap: 14px;">
                            <button type="button" @click="guests = Math.max(1, guests - 1)"
                                    class="calm-press calm-round flex items-center justify-center text-black"
                                    style="width: 36px; height: 36px; border-radius: 50%; border: 1px solid #E9E9E9; font-size: 18px;">−</button>
                            <span class="font-bold text-black tabular-nums" style="font-size: 16px; min-width: 22px; text-align: center;" x-text="guests"></span>
                            <button type="button" @click="guests = Math.min({{ (int) ($place->max_guests ?: 1) }}, guests + 1)"
                                    class="calm-press calm-round flex items-center justify-center text-black"
                                    style="width: 36px; height: 36px; border-radius: 50%; border: 1px solid #E9E9E9; font-size: 18px;">+</button>
                        </div>
                    </div>

                    {{-- Price breakdown --}}
                    <div style="margin-top: 20px; border-top: 1px solid #F1F1F1; padding-top: 16px;">
                        <div class="flex items-center justify-between" style="padding: 6px 0;">
                            <span class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;"><bdi dir="ltr">{{ number_format((int) $place->price) }} SR</bdi> × <span x-text="quote ? quote.days : ''"></span> {{ $isRtl ? 'أيام' : 'days' }}</span>
                            <span class="font-bold text-black tabular-nums" dir="ltr"><span x-text="fmtMoney(quote?.pricing.subtotal)"></span> SR</span>
                        </div>
                        <div class="flex items-center justify-between" style="padding: 6px 0;">
                            <span class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'الضريبة' : 'VAT' }}</span>
                            <span class="font-bold text-black tabular-nums" dir="ltr"><span x-text="fmtMoney(quote?.pricing.vat)"></span> SR</span>
                        </div>
                        <div class="flex items-center justify-between" style="padding: 10px 0; border-top: 1px solid #F1F1F1; margin-top: 6px;">
                            <span class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'الإجمالي' : 'Total' }}</span>
                            <span class="font-bold text-black tabular-nums" style="font-size: 17px;" dir="ltr"><span x-text="fmtMoney(quote?.pricing.total)"></span> SR</span>
                        </div>
                        <p x-show="quoteError" x-cloak class="{{ $fa }}" style="font-size: 12.5px; color: #dc2626; margin-top: 6px;" x-text="quoteError"></p>
                        <p x-show="submitError" x-cloak class="{{ $fa }}" style="font-size: 12.5px; color: #dc2626; margin-top: 6px;" x-text="submitError"></p>
                    </div>
                </div>
                <div class="shrink-0" style="padding: 12px 20px calc(16px + env(safe-area-inset-bottom)); border-top: 1px solid #F1F1F1;">
                    <button type="button" @click="proceed()" :disabled="!quote || submitting"
                            class="calm-press w-full font-bold text-white {{ $fa }}"
                            :style="'padding: 16px; border-radius: 18px; font-size: 16px; transition: opacity 0.2s; background-color: #F88379; box-shadow: 0 6px 12px rgba(248,131,121,0.3); opacity: ' + (quote && !submitting ? 1 : 0.5) + ';'">
                        <span x-text="submitting
                            ? '{{ $isRtl ? 'جاري التحويل…' : 'Redirecting…' }}'
                            : (@js(auth('api')->check()) ? '{{ $isRtl ? 'التالي — الدفع' : 'Next — pay' }}' : '{{ $isRtl ? 'التالي — تسجيل الدخول' : 'Next — sign in' }}')"></span>
                    </button>
                    <p class="text-center {{ $fa }}" style="margin-top: 8px; font-size: 11.5px; color: #AAAAAA;">
                        {{ $isRtl ? 'بالمتابعة، أوافق على شروط الحجز.' : 'By continuing, I agree to the booking terms.' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function calmBookingModal(init) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const AR_DAYS = ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'];
    const EN_DAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    // Parse 'YYYY-MM-DD' as a LOCAL date — new Date(str) is UTC midnight and
    // shifts a day for viewers west of UTC.
    const parseD = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const todayIso = iso(new Date());
    const MONTHS_AHEAD = 12;
    const gregFmt = new Intl.DateTimeFormat(init.isRtl ? 'ar' : 'en', { weekday: 'long', day: 'numeric', month: 'long' });

    // Dates handed over from search (?check_in/?check_out on the place URL).
    const qp = new URLSearchParams(window.location.search);
    const okIso = (s) => /^\d{4}-\d{2}-\d{2}$/.test(s || '');
    let qIn = qp.get('check_in'), qOut = qp.get('check_out');
    if (!okIso(qIn) || qIn < todayIso) { qIn = null; qOut = null; }
    else if (!okIso(qOut) || qOut < qIn) { qOut = qIn; }

    return {
        open: false,
        step: 'dates',
        months: [],
        unavailable: new Set(),
        availLoaded: false,
        checkIn: qIn,
        checkOut: qIn ? qOut : null,
        guests: 1,
        quote: null, quoteError: '', quoteSeq: 0,
        submitting: false, submitError: '',

        init() {
            // Resume a booking interrupted by the login step.
            try {
                const raw = sessionStorage.getItem('calm-pending-booking');
                if (raw) {
                    const p = JSON.parse(raw);
                    const fresh = Date.now() - (p.t || 0) < 3600000;
                    if (p.placeId === init.placeId && init.authed && fresh && okIso(p.checkIn) && p.checkIn >= todayIso) {
                        this.checkIn = p.checkIn;
                        this.checkOut = okIso(p.checkOut) && p.checkOut >= p.checkIn ? p.checkOut : p.checkIn;
                        this.guests = Math.min(init.maxGuests, Math.max(1, p.guests || 1));
                        sessionStorage.removeItem('calm-pending-booking');
                        this.$nextTick(() => this.openBooking());
                    } else if (!fresh || (p.placeId === init.placeId && init.authed)) {
                        sessionStorage.removeItem('calm-pending-booking');
                    }
                }
            } catch (e) { /* storage unavailable — flow still works, minus resume */ }
            this.$watch('guests', () => this.fetchQuote());
        },

        openBooking() {
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.step = this.checkIn && this.checkOut ? 'summary' : 'dates';
            if (!this.availLoaded) {
                this.availLoaded = true;
                this.buildMonths();
                this.loadAvail();
            }
            if (this.checkIn && this.checkOut && !this.quote) this.fetchQuote();
        },
        closeBooking() {
            if (!this.open) return;
            this.open = false;
            document.body.style.overflow = '';
        },
        toSummary() { if (this.quote) this.step = 'summary'; },

        async loadAvail() {
            try {
                const res = await fetch(`/api/places/${init.placeId}/unavailable-dates`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                (json.data?.unavailable_dates || []).forEach((d) => this.unavailable.add(d));
                this.buildMonths(); // re-render with strikethroughs
            } catch (e) { /* calendar still works; the server re-validates */ }
        },

        dayNames() { return init.isRtl ? AR_DAYS : EN_DAYS; },
        buildMonths() {
            const list = [];
            const now = new Date();
            const AR_MONTHS = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
            const EN_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            for (let i = 0; i < MONTHS_AHEAD; i++) {
                const first = new Date(now.getFullYear(), now.getMonth() + i, 1);
                const cells = [];
                for (let p = 0; p < first.getDay(); p++) cells.push({ key: `p${p}`, day: null });
                const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
                for (let d = 1; d <= days; d++) {
                    const date = iso(new Date(first.getFullYear(), first.getMonth(), d));
                    cells.push({ key: date, day: d, date, disabled: date < todayIso || this.unavailable.has(date) });
                }
                list.push({
                    key: `${first.getFullYear()}-${first.getMonth()}`,
                    label: `${(init.isRtl ? AR_MONTHS : EN_MONTHS)[first.getMonth()]} ${first.getFullYear()}`,
                    cells,
                });
            }
            this.months = list;
        },

        pickDay(date) {
            this.submitError = '';
            this.quoteError = '';
            const singleSelected = this.checkIn !== null && this.checkOut === this.checkIn;
            if (singleSelected && date > this.checkIn) {
                if (!this.rangeFree(this.checkIn, date)) {
                    this.quoteError = init.isRtl ? 'يوجد أيام محجوزة ضمن المدى المختار — اختر تواريخ أخرى.' : 'Some days in that range are booked — pick different dates.';
                    this.checkIn = date; this.checkOut = date;
                } else {
                    this.checkOut = date;
                }
                this.fetchQuote();
                return;
            }
            if (singleSelected && date === this.checkIn) return;
            this.checkIn = date;
            this.checkOut = date;
            this.fetchQuote();
        },
        rangeFree(a, b) {
            const cur = parseD(a); const end = parseD(b);
            while (cur < end) { if (this.unavailable.has(iso(cur))) return false; cur.setDate(cur.getDate() + 1); }
            return true;
        },
        hasRange() { return !!(this.checkIn && this.checkOut); },
        cellStyle(cell) {
            let css = 'height: 46px;';
            if (!cell.date || !this.hasRange()) return css;
            const BAND = 'rgba(0,0,0,0.05)';
            const s = cell.date === this.checkIn, e = cell.date === this.checkOut;
            const between = cell.date > this.checkIn && cell.date < this.checkOut;
            const inward = init.isRtl ? 'left' : 'right';
            const outward = init.isRtl ? 'right' : 'left';
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

        rangeTitle() {
            if (!this.checkIn || !this.checkOut) return init.isRtl ? 'اختر التواريخ' : 'Pick your dates';
            const n = Math.round((parseD(this.checkOut) - parseD(this.checkIn)) / 86400000) + 1;
            if (!init.isRtl) return n === 1 ? '1 day' : `${n} days`;
            return n === 1 ? 'يوم واحد' : (n === 2 ? 'يومان' : `${n} أيام`);
        },
        nightsLabel() {
            if (!this.quote) return '';
            const n = this.quote.days;
            if (!init.isRtl) return n === 1 ? '1 day' : `${n} days`;
            return n === 1 ? 'يوم واحد' : (n === 2 ? 'يومان' : `${n} أيام`);
        },
        fmtMoney(v) { return v == null ? '' : Number(v).toLocaleString(); },
        fmtDay(dateStr) {
            if (!dateStr) return '';
            try { return gregFmt.format(parseD(dateStr)); } catch (e) { return dateStr; }
        },
        checkoutDay() {
            if (!this.checkOut) return null;
            const d = parseD(this.checkOut);
            if (init.checkoutNextDay) d.setDate(d.getDate() + 1);
            return iso(d);
        },

        async fetchQuote() {
            if (!this.checkIn || !this.checkOut) return;
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
                    // Dates handed over from search may have gone stale — reopen the calendar.
                    if (data.guests_ok) this.step = 'dates';
                    return;
                }
                this.quote = data;
            } catch (e) { if (seq === this.quoteSeq) this.quoteError = init.isRtl ? 'تعذر حساب السعر — حاول مجدداً.' : 'Could not fetch the price — try again.'; }
        },

        proceed() {
            if (!this.quote || this.submitting) return;
            if (!init.authed) {
                // Login reloads the page — stash the picked stay so init()
                // reopens the summary right where the guest left off.
                try {
                    sessionStorage.setItem('calm-pending-booking', JSON.stringify({
                        placeId: init.placeId, checkIn: this.checkIn, checkOut: this.checkOut, guests: this.guests, t: Date.now(),
                    }));
                } catch (e) { /* no-op */ }
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
