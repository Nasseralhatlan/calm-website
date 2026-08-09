{{-- Search sheet — mobile-app parity: stacked-card ACCORDION
     (وين ؟ / متى ؟ / نوع المكان ؟ / المنطقة ؟). The sheet is viewport-fixed:
     exactly one section can be open, it takes the remaining height and
     scrolls internally, the other sections stay visible as collapsed rows.
     Clicking a collapsed row opens it (closing the rest); clicking the open
     section's title closes it. Type + area are multi-select.

     Window-event contract:
       open:   $dispatch('calm-open-search', { typeId?, step? })
       apply:  emits 'calm-search-apply' with
               { cityId, areaIds: [], typeIds: [], checkIn, checkOut }
       reset:  $dispatch('calm-search-reset')

     Needs $cities (with areas) + $placeTypes in scope (WebHomeService::data).
     $searchModalRedirect (default true): with no page listener the modal
     navigates to the home results URL. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $searchModalCatalog = [
        'cities' => $cities->map(fn ($c) => [
            'id' => $c->id,
            'name' => $isRtl ? $c->name_ar : $c->name_en,
            'avatar' => $c->avatar ?: '🏙️',
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

    $cardStyle = 'border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 50px rgba(0,0,0,0.06); overflow: hidden;';
    $expandedFlex = "'flex: 1 1 0%; min-height: 0;'";
    $collapsedFlex = "'flex: 0 0 auto;'";
@endphp
<div x-data="calmSearchModal(@js($searchModalCatalog), { redirect: @js((bool) ($searchModalRedirect ?? true)) })"
     x-on:calm-open-search.window="open($event.detail || {})"
     x-on:calm-search-reset.window="resetSelection()"
     x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex flex-col"
     style="background-color: rgba(250,250,250,0.75); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);"
     role="dialog" aria-modal="true">

    <div class="mx-auto w-full" style="max-width: 560px; padding: 16px 20px 0; flex: 1 1 0%; min-height: 0; display: flex; flex-direction: column;">
        {{-- Top: centered logo + close --}}
        <div class="shrink-0 flex items-center justify-center" style="position: relative; margin-bottom: 14px;">
            <img src="/assets/logo/logo.png" alt="Calm" style="height: 30px; width: auto;" draggable="false">
            <button type="button" @click="close()" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                    class="calm-press calm-round flex items-center justify-center bg-white text-black"
                    style="position: absolute; inset-inline-start: 0; width: 42px; height: 42px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 16px;">✕</button>
        </div>

        {{-- Accordion --}}
        <div style="flex: 1 1 0%; min-height: 0; display: flex; flex-direction: column; gap: 12px; padding-bottom: 100px;">

            {{-- ── وين ؟ ── --}}
            <div class="bg-white flex flex-col" style="{{ $cardStyle }}" :style="active === 'where' ? {!! $expandedFlex !!} : {!! $collapsedFlex !!}">
                <button type="button" x-show="active !== 'where'" @click="toggle('where')"
                        class="flex items-center justify-between w-full {{ $fa }}" style="padding: 19px 24px;">
                    <span class="font-semibold" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'وين ؟' : 'Where?' }}</span>
                    <span class="font-bold text-black" style="font-size: 15px;" x-text="cityLabel()"></span>
                </button>
                <div x-show="active === 'where'" class="flex flex-col" style="padding: 20px 24px; flex: 1 1 0%; min-height: 0;">
                    <button type="button" @click="toggle('where')" class="shrink-0 w-full text-start">
                        <h3 class="font-bold text-black {{ $fa }}" style="font-size: 19px; line-height: 25px;">{{ $isRtl ? 'وين ؟' : 'Where?' }}</h3>
                    </button>
                    <input type="text" x-model="cityQuery" placeholder="{{ $isRtl ? 'ابحث عن مدينة' : 'Search for a city' }}"
                           class="shrink-0 w-full outline-none {{ $fa }}"
                           style="margin-top: 14px; background-color: #FAFAFA; border-radius: 16px; padding: 14px 18px; font-size: 14px; color: #000;">
                    <p class="shrink-0 {{ $fa }}" style="margin-top: 14px; font-size: 12px; color: #AAAAAA;">{{ $isRtl ? 'اشهر الوجهات' : 'Popular destinations' }}</p>
                    <div style="flex: 1 1 0%; min-height: 0; overflow-y: auto; margin-top: 4px;">
                        <template x-for="c in filteredCities()" :key="c.id">
                            <button type="button" @click="pickCity(c.id)"
                                    class="calm-press flex items-center w-full {{ $fa }}" style="padding: 8px 0; gap: 14px;">
                                <span class="flex items-center justify-center bg-white"
                                      style="width: 46px; height: 46px; border-radius: 15px; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 21px;"
                                      x-text="c.avatar"></span>
                                <span class="font-bold" style="font-size: 15px;"
                                      :style="sel.cityId === c.id ? 'color: #F88379;' : 'color: #000;'" x-text="c.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ── متى ؟ ── --}}
            <div class="bg-white flex flex-col" style="{{ $cardStyle }}" :style="active === 'when' ? {!! $expandedFlex !!} : {!! $collapsedFlex !!}">
                <button type="button" x-show="active !== 'when'" @click="toggle('when')"
                        class="flex items-center justify-between w-full {{ $fa }}" style="padding: 19px 24px;">
                    <span class="font-semibold" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'متى ؟' : 'When?' }}</span>
                    <span class="font-bold text-black" style="font-size: 15px;" x-text="whenShort()"></span>
                </button>
                <div x-show="active === 'when'" class="flex flex-col" style="padding: 20px 24px; flex: 1 1 0%; min-height: 0;">
                    <button type="button" @click="toggle('when')" class="shrink-0 w-full text-start">
                        <h3 class="font-bold text-black {{ $fa }}" style="font-size: 19px; line-height: 25px;">{{ $isRtl ? 'متى ؟' : 'When?' }}</h3>
                    </button>
                    <div style="flex: 1 1 0%; min-height: 0; overflow-y: auto; margin-top: 10px;">
                        <div class="grid" style="grid-template-columns: repeat(7, 1fr); position: sticky; top: 0; background: #fff; z-index: 1;">
                            <template x-for="w in weekdays" :key="w">
                                <div class="text-center {{ $fa }}" style="font-size: 11px; color: #AAAAAA; padding-bottom: 6px;" x-text="w"></div>
                            </template>
                        </div>
                        <template x-for="m in months" :key="m.key">
                            <div style="margin-top: 8px;">
                                <div class="text-center font-bold text-black {{ $fa }}" style="font-size: 13px;" x-text="m.label"></div>
                                <div class="grid" style="grid-template-columns: repeat(7, 1fr); margin-top: 2px;">
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
                    {{-- Quick chips + next/skip (pinned under the calendar) --}}
                    <div class="shrink-0 grid grid-cols-3" style="gap: 8px; margin-top: 12px;">
                        <template x-for="ch in quickChips()" :key="ch.iso">
                            <button type="button" @click="sel.checkIn = ch.iso; sel.checkOut = ch.iso"
                                    class="calm-press text-center {{ $fa }}"
                                    :style="'padding: 8px 4px; border-radius: 16px; border: 1.5px solid ' + (sel.checkIn === ch.iso && sel.checkOut === ch.iso ? '#000' : '#E9E9E9') + ';'">
                                <span class="block font-bold text-black" style="font-size: 12px;" x-text="ch.label"></span>
                                <span class="block" dir="ltr" style="font-size: 11px; color: #AAAAAA; margin-top: 1px;" x-text="ch.sub"></span>
                            </button>
                        </template>
                    </div>
                    <button type="button" @click="advanceFrom('when')"
                            class="calm-press shrink-0 w-full font-bold {{ $fa }}"
                            :class="sel.checkIn ? 'text-white' : 'text-black'"
                            :style="'margin-top: 10px; padding: 14px; border-radius: 18px; font-size: 14px; background-color: ' + (sel.checkIn ? '#000' : '#F5F5F5') + ';'"
                            x-text="sel.checkIn ? '{{ $isRtl ? 'التالي' : 'Next' }}' : '{{ $isRtl ? 'تخطّى' : 'Skip' }}'"></button>
                </div>
            </div>

            {{-- ── نوع المكان ؟ ── --}}
            <div class="bg-white flex flex-col" style="{{ $cardStyle }}" :style="active === 'type' ? {!! $expandedFlex !!} : {!! $collapsedFlex !!}">
                <button type="button" x-show="active !== 'type'" @click="toggle('type')"
                        class="flex items-center justify-between w-full {{ $fa }}" style="padding: 19px 24px;">
                    <span class="font-semibold" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'نوع المكان ؟' : 'Place type?' }}</span>
                    <span class="font-bold text-black" style="font-size: 15px;" x-text="typeLabel()"></span>
                </button>
                <div x-show="active === 'type'" class="flex flex-col" style="padding: 20px 24px; flex: 1 1 0%; min-height: 0;">
                    <button type="button" @click="toggle('type')" class="shrink-0 w-full text-start">
                        <h3 class="font-bold text-black {{ $fa }}" style="font-size: 19px; line-height: 25px;">{{ $isRtl ? 'نوع المكان ؟' : 'Place type?' }}</h3>
                        <p class="{{ $fa }}" style="margin-top: 2px; font-size: 12px; color: #AAAAAA;">{{ $isRtl ? 'اختر نوعاً أو أكثر' : 'Pick one or more' }}</p>
                    </button>
                    <div style="flex: 1 1 0%; min-height: 0; overflow-y: auto; margin-top: 14px;">
                        <div class="flex flex-wrap content-start" style="gap: 10px;">
                            <template x-for="t in cat.types" :key="t.id">
                                <button type="button" @click="toggleIn(sel.typeIds, t.id)"
                                        class="calm-press calm-round inline-flex items-center font-bold text-black {{ $fa }}"
                                        :style="'padding: 12px 20px; border-radius: 999px; gap: 7px; font-size: 13px; border: 1.5px solid ' + (sel.typeIds.includes(t.id) ? '#000' : '#E9E9E9') + ';'">
                                    <span x-text="t.icon"></span><span x-text="t.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <button type="button" @click="advanceFrom('type')"
                            class="calm-press shrink-0 w-full font-bold text-white {{ $fa }}"
                            style="margin-top: 14px; padding: 14px; border-radius: 18px; font-size: 14px; background-color: #000;">
                        {{ $isRtl ? 'التالي' : 'Next' }}
                    </button>
                </div>
            </div>

            {{-- ── المنطقة ؟ ── --}}
            <div class="bg-white flex flex-col" x-show="cityAreas().length > 0"
                 style="{{ $cardStyle }}" :style="active === 'area' ? {!! $expandedFlex !!} : {!! $collapsedFlex !!}">
                <button type="button" x-show="active !== 'area'" @click="toggle('area')"
                        class="flex items-center justify-between w-full {{ $fa }}" style="padding: 19px 24px;">
                    <span class="font-semibold" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'المنطقة ؟' : 'Area?' }}</span>
                    <span class="font-bold text-black" style="font-size: 15px;" x-text="areaLabel()"></span>
                </button>
                <div x-show="active === 'area'" class="flex flex-col" style="padding: 20px 24px; flex: 1 1 0%; min-height: 0;">
                    <button type="button" @click="toggle('area')" class="shrink-0 w-full text-start">
                        <h3 class="font-bold text-black {{ $fa }}" style="font-size: 19px; line-height: 25px;">{{ $isRtl ? 'المنطقة ؟' : 'Area?' }}</h3>
                        <p class="{{ $fa }}" style="margin-top: 2px; font-size: 12px; color: #AAAAAA;">{{ $isRtl ? 'اختر منطقة أو أكثر' : 'Pick one or more' }}</p>
                    </button>
                    <div style="flex: 1 1 0%; min-height: 0; overflow-y: auto; margin-top: 14px;">
                        <div class="flex flex-wrap content-start" style="gap: 10px;">
                            <template x-for="a in cityAreas()" :key="a.id">
                                <button type="button" @click="toggleIn(sel.areaIds, a.id)"
                                        class="calm-press calm-round font-bold text-black {{ $fa }}"
                                        :style="'padding: 12px 20px; border-radius: 999px; font-size: 13px; border: 1.5px solid ' + (sel.areaIds.includes(a.id) ? '#000' : '#E9E9E9') + ';'"
                                        x-text="a.name"></button>
                            </template>
                        </div>
                    </div>
                    <button type="button" @click="apply()"
                            class="calm-press shrink-0 w-full font-bold text-white {{ $fa }}"
                            style="margin-top: 14px; padding: 14px; border-radius: 18px; font-size: 14px; background-color: #000;">
                        {{ $isRtl ? 'ابحث' : 'Search' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Fixed footer: امسح الكل + ابحث --}}
    <div class="fixed inset-x-0 bottom-0" style="padding: 14px 20px calc(18px + env(safe-area-inset-bottom, 0px));">
        <div class="mx-auto flex items-center justify-between" style="max-width: 560px;">
            <button type="button" @click="resetSelection()"
                    class="font-bold text-black underline {{ $fa }}" style="font-size: 14px;">{{ $isRtl ? 'امسح الكل' : 'Clear all' }}</button>
            <button type="button" @click="apply()"
                    class="calm-press calm-round inline-flex items-center font-bold text-white {{ $fa }}"
                    style="padding: 15px 30px; border-radius: 9999px; gap: 9px; font-size: 15px; background-color: #F88379; box-shadow: 0 6px 12px rgba(248,131,121,0.3);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round">
                    <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                </svg>
                <span>{{ $isRtl ? 'ابحث' : 'Search' }}</span>
            </button>
        </div>
    </div>
</div>

<script>
    if (!window.calmSearchModal) {
        window.calmSearchModal = function (catalog, opts) {
            const LOCALE = @js($locale);
            const AR = LOCALE === 'ar';

            return {
                cat: catalog,
                opts: opts || {},
                modalOpen: false,
                active: 'where',
                cityQuery: '',
                sel: { cityId: null, areaIds: [], typeIds: [], checkIn: null, checkOut: null },
                months: [],
                weekdays: [],

                init() {
                    this.buildCalendar();
                    // Seed from the URL so reopening keeps the applied search.
                    const q = new URLSearchParams(window.location.search);
                    const cityId = q.get('city');
                    if (cityId && this.cat.cities.some((c) => c.id === cityId)) {
                        const list = (v) => (v || '').split(',').filter(Boolean);
                        this.sel.cityId = cityId;
                        this.sel.areaIds = list(q.get('area'));
                        this.sel.typeIds = list(q.get('type'));
                        const ci = q.get('in'), co = q.get('out');
                        if (ci && /^\d{4}-\d{2}-\d{2}$/.test(ci)) {
                            this.sel.checkIn = ci;
                            this.sel.checkOut = (co && /^\d{4}-\d{2}-\d{2}$/.test(co)) ? co : ci;
                        }
                        this.broadcastState();
                    }
                },

                // ── Open / close / accordion ──
                open(detail) {
                    if (detail.typeId && this.cat.types.some((t) => t.id === detail.typeId)) {
                        if (!this.sel.typeIds.includes(detail.typeId)) this.sel.typeIds.push(detail.typeId);
                    }
                    const map = { city: 'where', where: 'where', when: 'when', type: 'type', area: 'area' };
                    this.active = map[detail.step]
                        || (detail.typeId && this.sel.cityId ? 'type' : 'where');
                    this.modalOpen = true;
                    document.body.style.overflow = 'hidden';
                },
                close() {
                    this.modalOpen = false;
                    document.body.style.overflow = '';
                },
                // One open section max; clicking the open one closes it.
                toggle(section) {
                    this.active = this.active === section ? null : section;
                },
                advanceFrom(section) {
                    const order = ['where', 'when', 'type', 'area'];
                    const next = order[order.indexOf(section) + 1];
                    if (next === 'area' && this.cityAreas().length === 0) { this.apply(); return; }
                    this.active = next || null;
                },
                pickCity(id) {
                    if (this.sel.cityId !== id) this.sel.areaIds = [];
                    this.sel.cityId = id;
                    this.active = 'when';
                },
                toggleIn(list, id) {
                    const i = list.indexOf(id);
                    if (i >= 0) list.splice(i, 1); else list.push(id);
                },
                apply() {
                    if (!this.sel.cityId) { this.active = 'where'; return; }
                    this.broadcastState();
                    this.close();
                    const s = this.sel;
                    const selection = { cityId: s.cityId, areaIds: [...s.areaIds], typeIds: [...s.typeIds], checkIn: s.checkIn, checkOut: s.checkOut };
                    if (this.opts.redirect) {
                        const q = new URLSearchParams({ city: selection.cityId });
                        if (selection.areaIds.length) q.set('area', selection.areaIds.join(','));
                        if (selection.typeIds.length) q.set('type', selection.typeIds.join(','));
                        if (selection.checkIn) { q.set('in', selection.checkIn); q.set('out', selection.checkOut || selection.checkIn); }
                        window.location.href = `/?${q}`;
                        return;
                    }
                    this.$dispatch('calm-search-apply', selection);
                },
                resetSelection() {
                    this.sel = { cityId: null, areaIds: [], typeIds: [], checkIn: null, checkOut: null };
                    this.cityQuery = '';
                    this.active = 'where';
                    this.broadcastState();
                },

                // ── Labels / lookups ──
                filteredCities() {
                    const q = this.cityQuery.trim();
                    return q ? this.cat.cities.filter((c) => c.name.includes(q)) : this.cat.cities;
                },
                cityAreas() {
                    return this.cat.cities.find((c) => c.id === this.sel.cityId)?.areas || [];
                },
                cityLabel() {
                    return this.cat.cities.find((c) => c.id === this.sel.cityId)?.name || (AR ? 'أي مدينة' : 'Anywhere');
                },
                typeLabel() {
                    const names = this.cat.types.filter((t) => this.sel.typeIds.includes(t.id)).map((t) => t.name);
                    if (!names.length) return AR ? 'اختر نوع' : 'Choose type';
                    return names.length === 1 ? names[0] : `${names[0]} +${names.length - 1}`;
                },
                areaLabel() {
                    const names = this.cityAreas().filter((a) => this.sel.areaIds.includes(a.id)).map((a) => a.name);
                    if (!names.length) return AR ? 'اختر منطقة' : 'Choose area';
                    return names.length === 1 ? names[0] : `${names[0]} +${names.length - 1}`;
                },
                whenShort() {
                    return this.sel.checkIn ? this.whenLabel() : (AR ? 'أي تاريخ' : 'Any date');
                },
                whenLabel() {
                    if (!this.sel.checkIn) return '';
                    // Pin Gregorian for Arabic — plain ar-SA defaults to Hijri.
                    const f = (iso) => new Intl.DateTimeFormat(AR ? 'ar-SA-u-ca-gregory' : 'en', { day: 'numeric', month: 'short' })
                        .format(new Date(iso + 'T00:00:00'));
                    return this.sel.checkOut && this.sel.checkOut !== this.sel.checkIn
                        ? `${f(this.sel.checkIn)} – ${f(this.sel.checkOut)}`
                        : f(this.sel.checkIn);
                },
                broadcastState() {
                    this.$dispatch('calm-search-state', {
                        cityName: this.cat.cities.find((c) => c.id === this.sel.cityId)?.name || null,
                        whenText: this.sel.checkIn ? this.whenLabel() : null,
                        typeName: this.sel.typeIds.length ? this.typeLabel() : null,
                        areaName: this.sel.areaIds.length ? this.areaLabel() : null,
                    });
                },

                // ── Quick chips: next Thu / Fri / Sat («الخميس الجاي» + Aug 13) ──
                quickChips() {
                    const names = AR ? { 4: 'الخميس الجاي', 5: 'الجمعة الجاي', 6: 'السبت الجاي' } : { 4: 'Next Thursday', 5: 'Next Friday', 6: 'Next Saturday' };
                    const today = new Date(); today.setHours(0, 0, 0, 0);
                    return [4, 5, 6].map((dow) => {
                        const ahead = ((dow - today.getDay()) + 7) % 7 || 7;
                        const d = new Date(today); d.setDate(d.getDate() + ahead);
                        const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                        return { iso, label: names[dow], sub: new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric' }).format(d) };
                    });
                },

                // ── Calendar (6 months, one-tap = one-day, later tap extends) ──
                buildCalendar() {
                    const loc = AR ? 'ar-SA-u-ca-gregory' : 'en';
                    const wd = new Intl.DateTimeFormat(loc, { weekday: 'narrow' });
                    const nf = new Intl.NumberFormat(AR ? 'ar' : 'en');
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
                    let st = 'height: 42px; display: flex; align-items: center; justify-content: center;';
                    if (!c) return st;
                    if (!c.past) st += 'cursor: pointer;';
                    const { checkIn: a, checkOut: b } = this.sel;
                    if (a && b && a !== b) {
                        const rtl = document.documentElement.dir === 'rtl';
                        const band = '#F1F1F1';
                        if (c.iso > a && c.iso < b) st += `background: ${band};`;
                        else if (c.iso === a) st += `background: linear-gradient(to ${rtl ? 'left' : 'right'}, transparent 50%, ${band} 50%);`;
                        else if (c.iso === b) st += `background: linear-gradient(to ${rtl ? 'left' : 'right'}, ${band} 50%, transparent 50%);`;
                    }
                    return st;
                },
                dayStyle(c) {
                    let st = 'width: 36px; height: 36px; border-radius: 50%; corner-shape: round !important; -webkit-corner-shape: round !important; display: flex; align-items: center; justify-content: center; font-size: 13px;';
                    if (c.past) return st + 'color: #d1d5db; text-decoration: line-through;';
                    if (c.iso === this.sel.checkIn || c.iso === this.sel.checkOut) return st + 'background: #000; color: #fff; font-weight: 700;';
                    return st + 'color: #000;';
                },
            };
        };
    }
</script>
