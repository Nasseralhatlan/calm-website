{{-- Reusable search modal — a 4-step wizard (المدينة → متى → النوع → الحي).
     Self-contained Alpine component; include it once on any page and talk to
     it with window events:

       open:   $dispatch('calm-open-search', { typeId?: '…' })
       apply:  it emits 'calm-search-apply' with
               { cityId, areaId, typeId, checkIn, checkOut }
       reset:  $dispatch('calm-search-reset') clears its selection

     Needs $cities (with areas) + $placeTypes in scope (WebHomeService::data).
     $searchModalRedirect (default true): with no page listener the modal
     navigates to the home results URL; the home page passes false and renders
     results in place. The area step only appears when the chosen city has
     areas. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $searchModalCatalog = [
        'cities' => $cities->map(fn ($c) => [
            'id' => $c->id,
            'name' => $isRtl ? $c->name_ar : $c->name_en,
            'areas' => $c->areas->map(fn ($a) => [
                'id' => $a->id,
                'name' => $isRtl ? $a->name_ar : $a->name_en,
            ])->values(),
        ])->values(),
        'types' => $placeTypes->map(fn ($t) => [
            'id' => $t->id,
            'name' => $isRtl ? $t->name_ar : $t->name_en,
            'icon' => $t->icon ?: '🏠',
        ])->values(),
    ];
    $stepTitles = [
        'city' => $isRtl ? 'المدينة' : 'City',
        'when' => $isRtl ? 'متى' : 'When',
        'type' => $isRtl ? 'النوع' : 'Type',
        'area' => $isRtl ? 'الحي' : 'Area',
    ];
@endphp
<div x-data="calmSearchModal(@js($searchModalCatalog), { redirect: @js((bool) ($searchModalRedirect ?? true)) })"
     x-on:calm-open-search.window="open($event.detail || {})"
     x-on:calm-search-reset.window="resetSelection()"
     x-show="modalOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true">
    <div class="absolute inset-0" style="background: rgba(0,0,0,0.5);" @click="close()"></div>

    <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto bg-white flex flex-col overflow-hidden calm-modal-panel">
        {{-- Header: close + labeled wizard steps (المدينة · متى · النوع · الحي) --}}
        <div class="border-b border-[#F1F1F1] shrink-0" style="padding: 12px 20px 0;">
            <div class="flex items-center justify-between">
                <button type="button" @click="close()" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                        class="calm-press flex items-center justify-center text-[#717171] hover:text-[#222] hover:bg-[#f7f7f7] transition-colors"
                        style="width: 34px; height: 34px; border-radius: 50%; font-size: 16px;">✕</button>
                <div class="flex items-center justify-center" style="gap: 6px;">
                    <template x-for="(k, i) in stepKeys()" :key="k">
                        <button type="button" @click="goToStep(i)"
                                class="flex flex-col items-center transition-colors {{ $fa }}"
                                :class="i === step ? 'text-[#1A1A1A]' : 'text-[#9CA3AF] hover:text-[#6B7280]'"
                                style="padding: 8px 10px 12px; gap: 6px; position: relative;">
                            <span :class="i === step ? 'font-bold' : 'font-semibold'" style="font-size: 13px; line-height: 16px;"
                                  x-text="stepTitles[k]"></span>
                            <span style="position: absolute; bottom: 0; height: 3px; border-radius: 3px 3px 0 0; transition: all 0.25s;"
                                  :style="i === step ? 'width: 22px; background: #F88379;' : 'width: 0; background: transparent;'"></span>
                        </button>
                    </template>
                </div>
                <div style="width: 34px;"></div>
            </div>
        </div>

        {{-- Body: one wizard step at a time --}}
        <div class="flex-1 overflow-y-auto" style="padding: 20px;">

            {{-- المدينة --}}
            <section x-show="currentKey() === 'city'">
                <div class="flex flex-wrap" style="gap: 8px;">
                    <template x-for="c in cat.cities" :key="c.id">
                        <button type="button" @click="pickCity(c.id)"
                                class="calm-press text-[13px] font-semibold transition-all {{ $fa }}"
                                :class="sel.cityId === c.id ? 'bg-[#1A1A1A] text-white border-[#1A1A1A]' : 'bg-white text-[#1A1A1A] border-[#E5E7EB] hover:border-[#1A1A1A]'"
                                style="padding: 12px 20px; border-radius: 999px; border-width: 1px; border-style: solid;"
                                x-text="c.name"></button>
                    </template>
                </div>
                <p class="text-[12px] text-[#6B7280] {{ $fa }}" style="margin-top: 14px;">
                    {{ $isRtl ? 'اختر المدينة للمتابعة.' : 'Pick a city to continue.' }}
                </p>
            </section>

            {{-- متى --}}
            <section x-show="currentKey() === 'when'" x-cloak>
                <div class="border border-[#F1F1F1]" style="border-radius: 18px; padding: 12px 10px; max-height: 320px; overflow-y: auto;">
                    <div class="grid" style="grid-template-columns: repeat(7, 1fr);">
                        <template x-for="w in weekdays" :key="w">
                            <div class="text-center text-[11px] text-[#b0b0b0] {{ $fa }}" x-text="w"></div>
                        </template>
                    </div>
                    <template x-for="m in months" :key="m.key">
                        <div style="margin-top: 12px;">
                            <div class="text-center text-[14px] font-bold text-[#1A1A1A] {{ $fa }}" x-text="m.label"></div>
                            <div class="grid" style="grid-template-columns: repeat(7, 1fr); margin-top: 6px;">
                                <template x-for="(c, ci) in m.cells" :key="m.key + '-' + ci">
                                    <div :style="cellStyle(c)" @click="c && !c.past && pickDay(c.iso)">
                                        <template x-if="c">
                                            <div :style="dayStyle(c)" x-text="c.d"></div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="flex items-center justify-between" style="margin-top: 10px;">
                    <p class="text-[12px] text-[#6B7280] {{ $fa }}"
                       x-text="sel.checkIn ? whenLabel() : '{{ $isRtl ? 'اختياري — يمكنك التخطي.' : 'Optional — you can skip.' }}'"></p>
                    <button type="button" x-show="sel.checkIn" x-cloak @click="sel.checkIn = null; sel.checkOut = null"
                            class="text-[12px] font-semibold text-[#717171] underline {{ $fa }}">{{ $isRtl ? 'مسح التواريخ' : 'Clear dates' }}</button>
                </div>
            </section>

            {{-- النوع --}}
            <section x-show="currentKey() === 'type'" x-cloak>
                <div class="flex flex-wrap" style="gap: 8px;">
                    <template x-for="t in cat.types" :key="t.id">
                        <button type="button" @click="sel.typeId = sel.typeId === t.id ? null : t.id"
                                class="calm-press inline-flex items-center text-[13px] font-semibold transition-all {{ $fa }}"
                                :class="sel.typeId === t.id ? 'bg-[#1A1A1A] text-white border-[#1A1A1A]' : 'bg-white text-[#1A1A1A] border-[#E5E7EB] hover:border-[#1A1A1A]'"
                                style="padding: 12px 20px; border-radius: 999px; border-width: 1px; border-style: solid; gap: 6px;">
                            <span x-text="t.icon"></span><span x-text="t.name"></span>
                        </button>
                    </template>
                </div>
                <p class="text-[12px] text-[#6B7280] {{ $fa }}" style="margin-top: 14px;">
                    {{ $isRtl ? 'اختياري — اتركه فارغاً لكل الأنواع.' : 'Optional — leave empty for all types.' }}
                </p>
            </section>

            {{-- الحي --}}
            <section x-show="currentKey() === 'area'" x-cloak>
                <div class="flex flex-wrap" style="gap: 8px;">
                    <button type="button" @click="sel.areaId = null"
                            class="calm-press text-[13px] font-semibold transition-all {{ $fa }}"
                            :class="!sel.areaId ? 'bg-[#1A1A1A] text-white border-[#1A1A1A]' : 'bg-white text-[#1A1A1A] border-[#E5E7EB] hover:border-[#1A1A1A]'"
                            style="padding: 12px 20px; border-radius: 999px; border-width: 1px; border-style: solid;">
                        {{ $isRtl ? 'كل الأحياء' : 'All areas' }}
                    </button>
                    <template x-for="a in cityAreas()" :key="a.id">
                        <button type="button" @click="sel.areaId = sel.areaId === a.id ? null : a.id"
                                class="calm-press text-[13px] font-semibold transition-all {{ $fa }}"
                                :class="sel.areaId === a.id ? 'bg-[#1A1A1A] text-white border-[#1A1A1A]' : 'bg-white text-[#1A1A1A] border-[#E5E7EB] hover:border-[#1A1A1A]'"
                                style="padding: 12px 20px; border-radius: 999px; border-width: 1px; border-style: solid;"
                                x-text="a.name"></button>
                    </template>
                </div>
            </section>
        </div>

        {{-- Footer: clear · back / next / search --}}
        <div class="flex items-center justify-between border-t border-[#F1F1F1] bg-white shrink-0" style="padding: 14px 20px; gap: 10px;">
            <button type="button" @click="resetSelection()"
                    class="text-[13px] font-semibold text-[#717171] underline {{ $fa }}">{{ $isRtl ? 'مسح الكل' : 'Clear all' }}</button>
            <div class="flex items-center" style="gap: 8px;">
                <button type="button" x-show="step > 0" x-cloak @click="back()"
                        class="calm-press text-[14px] font-bold text-[#1A1A1A] border border-[#E5E7EB] hover:border-[#1A1A1A] bg-white transition-all {{ $fa }}"
                        style="padding: 12px 24px; border-radius: 18px;">
                    {{ $isRtl ? 'رجوع' : 'Back' }}
                </button>
                <button type="button" @click="next()" :disabled="!canNext()"
                        class="calm-press inline-flex items-center font-bold text-white bg-[#F88379] hover:bg-[#E66E64] disabled:opacity-50 disabled:cursor-not-allowed transition-colors {{ $fa }}"
                        style="padding: 12px 32px; border-radius: 18px; gap: 8px; font-size: 15px;">
                    <svg x-show="isLastStep()" x-cloak width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                    <span x-text="isLastStep() ? '{{ $isRtl ? 'بحث' : 'Search' }}' : '{{ $isRtl ? 'التالي' : 'Next' }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    if (!window.calmSearchModal) {
        window.calmSearchModal = function (catalog, opts) {
            const LOCALE = @js($locale);

            return {
                cat: catalog,
                opts: opts || {},
                stepTitles: @js($stepTitles),
                modalOpen: false,
                step: 0,
                sel: { cityId: null, areaId: null, typeId: null, checkIn: null, checkOut: null },
                months: [],
                weekdays: [],

                init() {
                    this.buildCalendar();
                    // Seed from the URL so «تعديل البحث» reopens with the
                    // applied selection after a refresh/shared link.
                    const q = new URLSearchParams(window.location.search);
                    const cityId = q.get('city');
                    if (cityId && this.cat.cities.some((c) => c.id === cityId)) {
                        this.sel.cityId = cityId;
                        this.sel.areaId = q.get('area') || null;
                        this.sel.typeId = q.get('type') || null;
                        const ci = q.get('in'), co = q.get('out');
                        if (ci && /^\d{4}-\d{2}-\d{2}$/.test(ci)) {
                            this.sel.checkIn = ci;
                            this.sel.checkOut = (co && /^\d{4}-\d{2}-\d{2}$/.test(co)) ? co : ci;
                        }
                        this.broadcastState();
                    }
                },

                // Keeps the top-bar pill segment labels in sync.
                broadcastState() {
                    this.$dispatch('calm-search-state', {
                        cityName: this.cat.cities.find((c) => c.id === this.sel.cityId)?.name || null,
                        whenText: this.sel.checkIn ? this.whenLabel() : null,
                        typeName: this.cat.types.find((t) => t.id === this.sel.typeId)?.name || null,
                        areaName: this.cityAreas().find((a) => a.id === this.sel.areaId)?.name || null,
                    });
                },

                // ── Wizard ──
                stepKeys() {
                    const keys = ['city', 'when', 'type'];
                    if (this.cityAreas().length > 0) keys.push('area');
                    return keys;
                },
                currentKey() { return this.stepKeys()[this.step] || 'city'; },
                stepTitle() {
                    return this.stepTitles[this.currentKey()];
                },
                isLastStep() { return this.step >= this.stepKeys().length - 1; },
                canNext() { return this.currentKey() !== 'city' || !!this.sel.cityId; },
                goToStep(i) {
                    // Later steps need a city first — bounce to the city step.
                    this.step = (i > 0 && !this.sel.cityId) ? 0 : Math.min(i, this.stepKeys().length - 1);
                },
                next() {
                    if (!this.canNext()) return;
                    if (this.isLastStep()) { this.apply(); return; }
                    this.step++;
                },
                back() { this.step = Math.max(0, this.step - 1); },

                open(detail) {
                    if (detail.typeId && this.cat.types.some((t) => t.id === detail.typeId)) {
                        this.sel.typeId = detail.typeId;
                    }
                    if (detail.step && this.stepKeys().includes(detail.step)) {
                        // A pill segment opens its own step directly.
                        this.step = this.stepKeys().indexOf(detail.step);
                    } else {
                        // Jump to the type step when a quick-type box opened us
                        // and the city is already known; otherwise start at city.
                        this.step = (detail.typeId && this.sel.cityId)
                            ? this.stepKeys().indexOf('type')
                            : 0;
                    }
                    this.modalOpen = true;
                    document.body.style.overflow = 'hidden';
                },
                close() {
                    this.modalOpen = false;
                    document.body.style.overflow = '';
                },
                apply() {
                    if (!this.sel.cityId) {
                        this.step = 0; // city is required — send them to pick one
                        return;
                    }
                    this.broadcastState();
                    this.close();
                    const selection = { ...this.sel };
                    if (this.opts.redirect) {
                        const q = new URLSearchParams({ city: selection.cityId });
                        if (selection.areaId) q.set('area', selection.areaId);
                        if (selection.typeId) q.set('type', selection.typeId);
                        if (selection.checkIn) { q.set('in', selection.checkIn); q.set('out', selection.checkOut || selection.checkIn); }
                        window.location.href = `/?${q}`;
                        return;
                    }
                    this.$dispatch('calm-search-apply', selection);
                },
                resetSelection() {
                    this.sel = { cityId: null, areaId: null, typeId: null, checkIn: null, checkOut: null };
                    this.step = 0;
                    this.broadcastState();
                },

                // ── Selection helpers ──
                pickCity(id) {
                    if (this.sel.cityId !== id) this.sel.areaId = null;
                    this.sel.cityId = id;
                },
                cityAreas() {
                    return this.cat.cities.find((c) => c.id === this.sel.cityId)?.areas || [];
                },
                whenLabel() {
                    if (!this.sel.checkIn) return '';
                    // Pin Gregorian for Arabic — plain ar-SA defaults to Hijri.
                    const f = (iso) => new Intl.DateTimeFormat(LOCALE === 'ar' ? 'ar-SA-u-ca-gregory' : 'en', { day: 'numeric', month: 'short' })
                        .format(new Date(iso + 'T00:00:00'));
                    return this.sel.checkOut && this.sel.checkOut !== this.sel.checkIn
                        ? `${f(this.sel.checkIn)} – ${f(this.sel.checkOut)}`
                        : f(this.sel.checkIn);
                },

                // ── Calendar (6 months, one-tap = one-day, later tap extends) ──
                buildCalendar() {
                    const loc = LOCALE === 'ar' ? 'ar-SA-u-ca-gregory' : 'en';
                    const wd = new Intl.DateTimeFormat(loc, { weekday: 'narrow' });
                    const nf = new Intl.NumberFormat(LOCALE === 'ar' ? 'ar' : 'en');
                    // Week starts Sunday — matches the app.
                    this.weekdays = [...Array(7)].map((_, i) => wd.format(new Date(2026, 2, i + 1))); // 2026-03-01 is a Sunday
                    const mf = new Intl.DateTimeFormat(loc, { month: 'long', year: 'numeric' });
                    const today = new Date(); today.setHours(0, 0, 0, 0);
                    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                    const out = [];
                    for (let i = 0; i < 6; i++) {
                        const first = new Date(today.getFullYear(), today.getMonth() + i, 1);
                        const cells = Array(first.getDay()).fill(null);
                        const daysIn = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
                        for (let d = 1; d <= daysIn; d++) {
                            const dt = new Date(first.getFullYear(), first.getMonth(), d);
                            cells.push({ d: nf.format(d), iso: iso(dt), past: dt < today });
                        }
                        out.push({ key: `${first.getFullYear()}-${first.getMonth()}`, label: mf.format(first), cells });
                    }
                    this.months = out;
                },
                pickDay(iso) {
                    const s = this.sel;
                    if (s.checkIn && s.checkIn === s.checkOut && iso > s.checkIn) {
                        s.checkOut = iso; // extend the one-day pick into a range
                    } else {
                        s.checkIn = iso; s.checkOut = iso; // fresh one-day selection
                    }
                },
                cellStyle(c) {
                    let st = 'height: 44px; display: flex; align-items: center; justify-content: center;';
                    if (!c) return st;
                    if (!c.past) st += 'cursor: pointer;';
                    const { checkIn: a, checkOut: b } = this.sel;
                    if (a && b && a !== b) {
                        const rtl = document.documentElement.dir === 'rtl';
                        const band = '#f4f4f4';
                        if (c.iso > a && c.iso < b) st += `background: ${band};`;
                        else if (c.iso === a) st += `background: linear-gradient(to ${rtl ? 'left' : 'right'}, transparent 50%, ${band} 50%);`;
                        else if (c.iso === b) st += `background: linear-gradient(to ${rtl ? 'left' : 'right'}, ${band} 50%, transparent 50%);`;
                    }
                    return st;
                },
                dayStyle(c) {
                    let st = 'width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;';
                    if (c.past) return st + 'color: #d1d5db; text-decoration: line-through;';
                    if (c.iso === this.sel.checkIn || c.iso === this.sel.checkOut) return st + 'background: #1A1A1A; color: #fff; font-weight: 700;';
                    return st + 'color: #1A1A1A;';
                },
            };
        };
    }
</script>
<style>
    .calm-modal-panel { border-radius: 24px 24px 0 0; max-height: 92vh; height: auto; }
    @media (min-width: 640px) {
        .calm-modal-panel { border-radius: 28px; max-width: 580px; max-height: 86vh; height: fit-content; }
    }
</style>
