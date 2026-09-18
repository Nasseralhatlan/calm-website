{{-- Booking dates modal (place page «احجز الآن»). Picks the stay, then «التالي»
     leaves the modal for the dedicated checkout PAGE (route book.checkout).
     If the visitor arrived from search with ?check_in/?check_out the modal is
     skipped entirely — reserve goes straight to checkout. Opening with #dates
     in the URL (checkout's «تغيير» link) opens the calendar immediately.
     Layout note: flex sizing is inline — min-h-0/flex-1 utilities weren't in
     the CSS build once, which clipped the footer button out of the panel. --}}
<div x-data="calmBookingModal(@js([
        'placeId' => $place->id,
        'checkoutUrl' => route('book.checkout', $place),
        'isRtl' => $isRtl,
    ]))"
     @calm-open-booking.window="openBooking()"
     @keydown.escape.window="closeBooking()">
    <div x-show="open" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.5);" @click="closeBooking()" x-transition.opacity></div>
        {{-- display comes from classes, NOT inline style — x-show rewrites the
             inline display property and would wipe `display: flex`. --}}
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:max-w-2xl bg-white overflow-hidden flex flex-col"
             style="border-radius: 28px 28px 0 0; corner-shape: squircle; max-height: calc(100% - 56px); height: calc(100% - 56px);"
             x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

            {{-- Header — filters-style X + centred title --}}
            <div class="relative flex items-center justify-center" style="flex: 0 0 auto; padding: 16px 20px 12px;">
                <button type="button" @click="closeBooking()" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                        class="calm-press calm-round absolute flex items-center justify-center bg-white text-black"
                        style="inset-inline-start: 16px; width: 42px; height: 42px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 16px;">✕</button>
                <h2 class="font-bold text-black {{ $fa }}" style="font-size: 18px;">{{ $isRtl ? 'اختر التواريخ' : 'Pick your dates' }}</h2>
            </div>

            <div class="text-center" style="flex: 0 0 auto; padding: 2px 20px 10px;">
                <div class="font-bold text-black {{ $fa }}" style="font-size: 15px;"
                     x-text="hasRange() ? rangeTitle() : '{{ $isRtl ? 'حدد يوم الوصول ويوم المغادرة' : 'Pick your arrival and departure days' }}'"></div>
                <p x-show="rangeError" x-cloak class="{{ $fa }}" style="font-size: 12.5px; color: #dc2626; margin-top: 4px;" x-text="rangeError"></p>
            </div>

            {{-- Day-of-week header --}}
            <div class="grid grid-cols-7 text-center" style="flex: 0 0 auto; padding: 0 20px 6px; border-bottom: 1px solid #F1F1F1;">
                <template x-for="(d, i) in dayNames()" :key="'dn' + i">
                    <span style="font-size: 12px; color: #AAAAAA;" x-text="d"></span>
                </template>
            </div>

            {{-- Scrollable months --}}
            <div style="flex: 1 1 0; min-height: 0; overflow-y: auto; padding: 6px 20px 20px; -webkit-overflow-scrolling: touch; overscroll-behavior: contain;">
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

            {{-- FIXED footer — «التالي» → the checkout page --}}
            <div style="flex: 0 0 auto; padding: 12px 20px calc(16px + env(safe-area-inset-bottom)); border-top: 1px solid #F1F1F1; background: #fff;">
                <button type="button" @click="next()" :disabled="!ready()"
                        class="calm-press w-full font-bold text-white {{ $fa }}"
                        :style="'padding: 16px; border-radius: 18px; font-size: 16px; transition: opacity 0.2s; background-color: #1A1A1A; opacity: ' + (ready() ? 1 : 0.35) + ';'">
                    <span x-text="ready() ? '{{ $isRtl ? 'التالي' : 'Next' }}' : '{{ $isRtl ? 'اختر التواريخ' : 'Pick your dates' }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function calmBookingModal(init) {
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    // Parse 'YYYY-MM-DD' as a LOCAL date — new Date(str) is UTC midnight and
    // shifts a day for viewers west of UTC.
    const parseD = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const todayIso = iso(new Date());
    const AR_DAYS = ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'];
    const EN_DAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    const MONTHS_AHEAD = 12;

    // Dates handed over from search (?check_in/?check_out on the place URL) —
    // reserve skips the calendar and goes straight to the checkout page.
    const qp = new URLSearchParams(window.location.search);
    const okIso = (s) => /^\d{4}-\d{2}-\d{2}$/.test(s || '');
    let qIn = qp.get('check_in'), qOut = qp.get('check_out');
    if (!okIso(qIn) || qIn < todayIso) { qIn = null; qOut = null; }
    else if (!okIso(qOut) || qOut < qIn) { qOut = qIn; }

    return {
        open: false,
        months: [],
        unavailable: new Set(),
        availLoaded: false,
        checkIn: null,
        checkOut: null,
        rangeError: '',

        init() {
            // Checkout's «تغيير التواريخ» link comes back with #dates.
            if (window.location.hash === '#dates') this.$nextTick(() => this.openBooking(true));
        },

        checkoutHref() {
            const q = new URLSearchParams({ check_in: this.checkIn, check_out: this.checkOut });
            return `${init.checkoutUrl}?${q}`;
        },
        openBooking(forceCalendar = false) {
            if (!forceCalendar && qIn && qOut) {
                const q = new URLSearchParams({ check_in: qIn, check_out: qOut });
                window.location.href = `${init.checkoutUrl}?${q}`;
                return;
            }
            this.open = true;
            window.calmTrack?.('view', 'dates', { place_id: init.placeId });
            document.body.style.overflow = 'hidden';
            if (!this.availLoaded) {
                this.availLoaded = true;
                this.buildMonths();
                this.loadAvail();
            }
        },
        closeBooking() {
            if (!this.open) return;
            this.open = false;
            document.body.style.overflow = '';
        },
        // The message is informational — after a taken-range tap the selection
        // resets to a valid one-day stay, which is still bookable.
        ready() { return !!(this.checkIn && this.checkOut); },
        next() {
            if (!this.ready()) return;
            window.calmTrack?.('click', 'continue_checkout', { place_id: init.placeId });
            window.location.href = this.checkoutHref();
        },

        async loadAvail() {
            try {
                const res = await fetch(`/api/places/${init.placeId}/unavailable-dates`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                (json.data?.unavailable_dates || []).forEach((d) => this.unavailable.add(d));
                this.buildMonths(); // re-render with strikethroughs
            } catch (e) { /* calendar still works; checkout re-validates */ }
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
            this.rangeError = '';
            const singleSelected = this.checkIn !== null && this.checkOut === this.checkIn;
            if (singleSelected && date > this.checkIn) {
                if (!this.rangeFree(this.checkIn, date)) {
                    this.rangeError = init.isRtl ? 'يوجد أيام محجوزة ضمن المدى المختار — اختر تواريخ أخرى.' : 'Some days in that range are booked — pick different dates.';
                    this.checkIn = date; this.checkOut = date;
                } else {
                    this.checkOut = date;
                }
                return;
            }
            if (singleSelected && date === this.checkIn) return;
            this.checkIn = date;
            this.checkOut = date;
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
    };
}

/**
 * Desktop place-page booking box: the same calendar state and rules as the
 * modal (availability, range picking, checkout hand-off), rendered one month
 * at a time inside a sticky card. `init()` is overridden — the modal's version
 * handles the #dates hash, which the box must not do.
 */
function calmBookingBox(init) {
    return {
        ...calmBookingModal(init),
        mi: 0,

        init() {
            this.buildMonths();
            this.loadAvail();

            // Prefill a stay carried over from search (?check_in/?check_out)
            // and open on its month.
            const qp = new URLSearchParams(window.location.search);
            const ok = (s) => /^\d{4}-\d{2}-\d{2}$/.test(s || '');
            const today = new Date();
            const todayIso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            const qIn = qp.get('check_in');
            const qOut = qp.get('check_out');
            if (ok(qIn) && qIn >= todayIso) {
                this.checkIn = qIn;
                this.checkOut = ok(qOut) && qOut >= qIn ? qOut : qIn;
                const [y, m] = qIn.split('-').map(Number);
                const idx = (y - today.getFullYear()) * 12 + (m - 1 - today.getMonth());
                this.mi = Math.max(0, Math.min(this.months.length - 1, idx));
            }
        },

        prevMonth() { if (this.mi > 0) this.mi--; },
        nextMonth() { if (this.mi < this.months.length - 1) this.mi++; },
    };
}
</script>
