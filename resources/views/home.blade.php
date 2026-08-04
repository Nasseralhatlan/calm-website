@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    // Catalog payload for the search modal — localized once server-side so the
    // JS never juggles both languages.
    $searchCatalog = [
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
@endphp

@section('title', $isRtl ? 'كالم — أماكن مختارة بعناية' : 'Calm — hand-picked stays')

@section('meta')
    <meta name="description" content="{{ __('brand_meta_description') }}">
    <meta property="og:title" content="{{ $isRtl ? 'كالم — أماكن مختارة بعناية' : 'Calm — hand-picked stays' }}">
    <meta property="og:description" content="{{ __('brand_meta_description') }}">
    <meta property="og:type" content="website">
@endsection

@section('body')
<div class="min-h-screen bg-white" x-data="calmHome(@js($searchCatalog))" x-init="init()">

    @include('partials._web_topbar', ['searchPill' => true])

    <main class="mx-auto w-full" style="max-width: 1200px; padding: 18px 16px 110px;">

        {{-- ══ Browse mode: quick types + curated lists ══ --}}
        <div x-show="mode === 'browse'">

            {{-- Quick place-type boxes — open the search modal with the type preselected --}}
            <section aria-label="{{ $isRtl ? 'أنواع الأماكن' : 'Place types' }}">
                <div class="flex overflow-x-auto calm-hide-scroll" style="gap: 12px; padding: 4px;">
                    @foreach($placeTypes as $t)
                        <button type="button" @click="openWithType(@js($t->id))"
                                class="shrink-0 flex flex-col items-center justify-center bg-white border border-[#ebebeb] hover:border-[#222] transition-all"
                                style="min-width: 120px; padding: 18px 22px; border-radius: 20px; gap: 8px; box-shadow: 0 4px 14px rgba(0,0,0,0.04);">
                            <span style="font-size: 30px; line-height: 1;">{{ $t->icon ?: '🏠' }}</span>
                            <span class="text-[13px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? $t->name_ar : $t->name_en }}</span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Curated lists (admin-managed) --}}
            @foreach($lists as $list)
                <section style="margin-top: 34px;" aria-label="{{ $isRtl ? $list->name_ar : $list->name_en }}">
                    <h2 class="text-[20px] font-bold text-[#222] {{ $fa }}">
                        @if($list->icon)<span style="margin-inline-end: 6px;">{{ $list->icon }}</span>@endif{{ $isRtl ? $list->name_ar : $list->name_en }}
                    </h2>
                    @if($isRtl ? $list->description_ar : $list->description_en)
                        <p class="text-[13px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">{{ $isRtl ? $list->description_ar : $list->description_en }}</p>
                    @endif
                    <div class="flex overflow-x-auto calm-hide-scroll" style="gap: 16px; margin-top: 14px; padding: 2px 2px 6px;">
                        @foreach($list->places as $p)
                            @include('partials._web_place_card', ['p' => $p, 'cardWidth' => '250px'])
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if($lists->isEmpty())
                <div class="text-center text-[#717171] {{ $fa }}" style="padding: 70px 0;">
                    {{ $isRtl ? 'لا توجد أماكن معروضة بعد — عد قريباً.' : 'Nothing to show yet — check back soon.' }}
                </div>
            @endif
        </div>

        {{-- ══ Results mode: 3-column grid + load more ══ --}}
        <div x-show="mode === 'results'" x-cloak>
            <div class="flex items-center justify-between flex-wrap" style="gap: 10px;">
                <div>
                    <h2 class="text-[20px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'نتائج البحث' : 'Search results' }}</h2>
                    <p class="text-[13px] text-[#717171] tabular-nums {{ $fa }}" x-show="!loadingGrid || items.length > 0"
                       x-text="total + ' {{ $isRtl ? 'مكان' : 'places' }}'"></p>
                </div>
                <div class="flex items-center" style="gap: 8px;">
                    <button type="button" @click="openSearch('city')"
                            class="text-[13px] font-bold text-[#222] border border-[#dddddd] hover:border-[#222] bg-white transition-all {{ $fa }}"
                            style="padding: 10px 18px; border-radius: 999px;">
                        {{ $isRtl ? 'تعديل البحث' : 'Edit search' }}
                    </button>
                    <button type="button" @click="clearSearch()"
                            class="text-[13px] font-semibold text-[#717171] hover:text-[#222] transition-colors {{ $fa }}"
                            style="padding: 10px 12px;">
                        ✕ {{ $isRtl ? 'مسح' : 'Clear' }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap: 24px; margin-top: 18px;">
                <template x-for="p in items" :key="p.id">
                    @include('partials._web_card_template')
                </template>
            </div>

            {{-- First-page loading --}}
            <div x-show="loadingGrid && items.length === 0" class="flex flex-col items-center text-[#717171] {{ $fa }}" style="padding: 70px 0; gap: 12px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#F88379" stroke-width="3" stroke-linecap="round">
                    <path d="M21 12a9 9 0 1 1-6.2-8.56">
                        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.9s" repeatCount="indefinite"/>
                    </path>
                </svg>
                <span class="text-[14px]">{{ $isRtl ? 'جاري البحث…' : 'Searching…' }}</span>
            </div>

            {{-- Empty / error states --}}
            <div x-show="!loadingGrid && !gridError && items.length === 0" x-cloak
                 class="text-center text-[#717171] {{ $fa }}" style="padding: 70px 0;">
                <div style="font-size: 34px; margin-bottom: 10px;">🔍</div>
                {{ $isRtl ? 'لا توجد نتائج مطابقة — جرّب تعديل البحث.' : 'No matching places — try adjusting your search.' }}
            </div>
            <div x-show="gridError" x-cloak class="text-center text-[#dc2626] {{ $fa }}" style="padding: 50px 0;">
                {{ $isRtl ? 'حدث خطأ أثناء البحث — أعد المحاولة.' : 'Something went wrong — please retry.' }}
            </div>

            {{-- Load more --}}
            <div class="text-center" style="margin-top: 28px;" x-show="hasMore && !gridError" x-cloak>
                <button type="button" @click="loadMore(searchParams())" :disabled="loadingGrid"
                        class="inline-flex items-center font-bold text-white bg-[#222] hover:bg-black disabled:opacity-60 transition-colors {{ $fa }}"
                        style="padding: 13px 34px; border-radius: 18px; gap: 8px; font-size: 14px;">
                    <svg x-show="loadingGrid" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <path d="M21 12a9 9 0 1 1-6.2-8.56">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.9s" repeatCount="indefinite"/>
                        </path>
                    </svg>
                    <span>{{ $isRtl ? 'عرض المزيد' : 'Load more' }}</span>
                </button>
            </div>
        </div>
    </main>

    {{-- ══ Search modal — city / when / type / area ══ --}}
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background: rgba(0,0,0,0.45);" @click="closeSearch()"></div>

        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto bg-white flex flex-col overflow-hidden calm-modal-panel">
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-[#ebebeb] shrink-0" style="padding: 16px 20px;">
                <h2 class="text-[17px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'ابحث عن مكانك' : 'Find your place' }}</h2>
                <button type="button" @click="closeSearch()" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                        class="flex items-center justify-center text-[#717171] hover:text-[#222] hover:bg-[#f7f7f7] transition-colors"
                        style="width: 34px; height: 34px; border-radius: 50%; font-size: 16px;">✕</button>
            </div>

            {{-- Scrollable body --}}
            <div class="flex-1 overflow-y-auto" style="padding: 6px 20px 20px;">

                {{-- المدينة --}}
                <section x-ref="sec_city" style="padding-top: 16px;">
                    <h3 class="text-[15px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'المدينة' : 'City' }} <span class="text-[#F88379]">*</span></h3>
                    <div class="flex flex-wrap" style="gap: 8px; margin-top: 12px;">
                        <template x-for="c in cat.cities" :key="c.id">
                            <button type="button" @click="pickCity(c.id)"
                                    class="text-[13px] font-semibold transition-all {{ $fa }}"
                                    :class="sel.cityId === c.id ? 'bg-[#222] text-white border-[#222]' : 'bg-white text-[#222] border-[#dddddd] hover:border-[#222]'"
                                    style="padding: 10px 18px; border-radius: 999px; border-width: 1px; border-style: solid;"
                                    x-text="c.name"></button>
                        </template>
                    </div>
                </section>

                {{-- متى --}}
                <section x-ref="sec_when" style="padding-top: 26px;">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[15px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'متى' : 'When' }}</h3>
                        <button type="button" x-show="sel.checkIn" x-cloak @click="sel.checkIn = null; sel.checkOut = null"
                                class="text-[12px] font-semibold text-[#717171] underline {{ $fa }}">{{ $isRtl ? 'مسح التواريخ' : 'Clear dates' }}</button>
                    </div>
                    <div class="border border-[#ebebeb]" style="border-radius: 18px; margin-top: 12px; padding: 12px 10px; max-height: 300px; overflow-y: auto;">
                        <div class="grid" style="grid-template-columns: repeat(7, 1fr);">
                            <template x-for="w in weekdays" :key="w">
                                <div class="text-center text-[11px] text-[#b0b0b0] {{ $fa }}" x-text="w"></div>
                            </template>
                        </div>
                        <template x-for="m in months" :key="m.key">
                            <div style="margin-top: 12px;">
                                <div class="text-center text-[14px] font-bold text-[#222] {{ $fa }}" x-text="m.label"></div>
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
                    <p class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 8px;" x-show="sel.checkIn" x-cloak x-text="whenLabel()"></p>
                </section>

                {{-- النوع --}}
                <section x-ref="sec_type" style="padding-top: 26px;">
                    <h3 class="text-[15px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'النوع' : 'Type' }}</h3>
                    <div class="flex flex-wrap" style="gap: 8px; margin-top: 12px;">
                        <template x-for="t in cat.types" :key="t.id">
                            <button type="button" @click="sel.typeId = sel.typeId === t.id ? null : t.id"
                                    class="inline-flex items-center text-[13px] font-semibold transition-all {{ $fa }}"
                                    :class="sel.typeId === t.id ? 'bg-[#222] text-white border-[#222]' : 'bg-white text-[#222] border-[#dddddd] hover:border-[#222]'"
                                    style="padding: 10px 18px; border-radius: 999px; border-width: 1px; border-style: solid; gap: 6px;">
                                <span x-text="t.icon"></span><span x-text="t.name"></span>
                            </button>
                        </template>
                    </div>
                </section>

                {{-- الحي --}}
                <section x-ref="sec_area" style="padding-top: 26px;">
                    <h3 class="text-[15px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'الحي' : 'Area' }}</h3>
                    <p x-show="!sel.cityId" class="text-[13px] text-[#717171] {{ $fa }}" style="margin-top: 8px;">
                        {{ $isRtl ? 'اختر المدينة أولاً.' : 'Pick a city first.' }}
                    </p>
                    <div x-show="sel.cityId" class="flex flex-wrap" style="gap: 8px; margin-top: 12px;">
                        <button type="button" @click="sel.areaId = null"
                                class="text-[13px] font-semibold transition-all {{ $fa }}"
                                :class="!sel.areaId ? 'bg-[#222] text-white border-[#222]' : 'bg-white text-[#222] border-[#dddddd] hover:border-[#222]'"
                                style="padding: 10px 18px; border-radius: 999px; border-width: 1px; border-style: solid;">
                            {{ $isRtl ? 'كل الأحياء' : 'All areas' }}
                        </button>
                        <template x-for="a in cityAreas()" :key="a.id">
                            <button type="button" @click="sel.areaId = sel.areaId === a.id ? null : a.id"
                                    class="text-[13px] font-semibold transition-all {{ $fa }}"
                                    :class="sel.areaId === a.id ? 'bg-[#222] text-white border-[#222]' : 'bg-white text-[#222] border-[#dddddd] hover:border-[#222]'"
                                    style="padding: 10px 18px; border-radius: 999px; border-width: 1px; border-style: solid;"
                                    x-text="a.name"></button>
                        </template>
                    </div>
                </section>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between border-t border-[#ebebeb] bg-white shrink-0" style="padding: 14px 20px; gap: 12px;">
                <button type="button" @click="resetSelection()"
                        class="text-[14px] font-semibold text-[#222] underline {{ $fa }}">{{ $isRtl ? 'مسح الكل' : 'Clear all' }}</button>
                <button type="button" @click="applySearch()" :disabled="!sel.cityId"
                        class="inline-flex items-center font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:opacity-50 disabled:cursor-not-allowed transition-colors {{ $fa }}"
                        style="padding: 13px 36px; border-radius: 18px; gap: 8px; font-size: 15px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                    <span>{{ $isRtl ? 'بحث' : 'Search' }}</span>
                </button>
            </div>
        </div>
    </div>

    @include('partials._web_floating_nav')
    @include('partials._web_footer')
</div>

@include('partials._web_grid_js')
<script>
    function calmHome(catalog) {
        return {
            ...calmGrid('/api/places/search'),

            cat: catalog,
            mode: 'browse',
            modalOpen: false,
            sel: { cityId: null, areaId: null, typeId: null, checkIn: null, checkOut: null },
            months: [],
            weekdays: [],

            init() {
                this.buildCalendar();
                // Restore a shared/refreshed search from the URL.
                const q = new URLSearchParams(window.location.search);
                const cityId = q.get('city');
                if (cityId && this.cat.cities.some((c) => c.id === cityId)) {
                    this.sel.cityId = cityId;
                    this.sel.areaId = q.get('area') || null;
                    this.sel.typeId = q.get('type') || null;
                    const ci = q.get('in'), co = q.get('out');
                    if (ci && /^\d{4}-\d{2}-\d{2}$/.test(ci)) { this.sel.checkIn = ci; this.sel.checkOut = (co && /^\d{4}-\d{2}-\d{2}$/.test(co)) ? co : ci; }
                    this.runSearch(1);
                }
            },

            // ── Modal ──
            openSearch(section) {
                this.modalOpen = true;
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => this.$refs['sec_' + section]?.scrollIntoView({ block: 'start' }));
            },
            closeSearch() {
                this.modalOpen = false;
                document.body.style.overflow = '';
            },
            openWithType(typeId) {
                this.sel.typeId = typeId;
                this.openSearch(this.sel.cityId ? 'type' : 'city');
            },
            pickCity(id) {
                if (this.sel.cityId !== id) this.sel.areaId = null;
                this.sel.cityId = id;
            },
            cityAreas() {
                return this.cat.cities.find((c) => c.id === this.sel.cityId)?.areas || [];
            },
            resetSelection() {
                this.sel = { cityId: null, areaId: null, typeId: null, checkIn: null, checkOut: null };
            },

            // ── Pill labels ──
            cityLabel() {
                return this.cat.cities.find((c) => c.id === this.sel.cityId)?.name
                    || (CALM_WEB.locale === 'ar' ? 'أي مدينة' : 'Anywhere');
            },
            typeLabel() {
                return this.cat.types.find((t) => t.id === this.sel.typeId)?.name
                    || (CALM_WEB.locale === 'ar' ? 'أي نوع' : 'Any type');
            },
            areaLabel() {
                return this.cityAreas().find((a) => a.id === this.sel.areaId)?.name
                    || (CALM_WEB.locale === 'ar' ? 'كل الأحياء' : 'All areas');
            },
            whenLabel() {
                if (!this.sel.checkIn) return CALM_WEB.locale === 'ar' ? 'أي وقت' : 'Anytime';
                const f = (iso) => new Intl.DateTimeFormat(CALM_WEB.locale === 'ar' ? 'ar' : 'en', { day: 'numeric', month: 'short' })
                    .format(new Date(iso + 'T00:00:00'));
                return this.sel.checkOut && this.sel.checkOut !== this.sel.checkIn
                    ? `${f(this.sel.checkIn)} – ${f(this.sel.checkOut)}`
                    : f(this.sel.checkIn);
            },

            // ── Calendar (6 months, one-tap = one-day stay, later tap extends) ──
            buildCalendar() {
                const loc = CALM_WEB.locale === 'ar' ? 'ar' : 'en';
                const wd = new Intl.DateTimeFormat(loc, { weekday: 'narrow' });
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
                        cells.push({ d, iso: iso(dt), past: dt < today });
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
                if (c.iso === this.sel.checkIn || c.iso === this.sel.checkOut) return st + 'background: #222; color: #fff; font-weight: 700;';
                return st + 'color: #222;';
            },

            // ── Search ──
            searchParams() {
                const q = { city_id: this.sel.cityId };
                if (this.sel.areaId) q.city_area_id = this.sel.areaId;
                if (this.sel.typeId) q['place_type_ids[]'] = this.sel.typeId;
                if (this.sel.checkIn) { q.check_in = this.sel.checkIn; q.check_out = this.sel.checkOut || this.sel.checkIn; }
                return q;
            },
            applySearch() {
                if (!this.sel.cityId) return;
                this.closeSearch();
                this.runSearch(1);
            },
            runSearch(page) {
                this.mode = 'results';
                this.fetchPage(page, this.searchParams());
                this.syncUrl();
            },
            clearSearch() {
                this.resetSelection();
                this.items = []; this.total = 0; this.hasMore = false;
                this.mode = 'browse';
                window.history.replaceState({}, '', window.location.pathname);
            },
            syncUrl() {
                const q = new URLSearchParams();
                q.set('city', this.sel.cityId);
                if (this.sel.areaId) q.set('area', this.sel.areaId);
                if (this.sel.typeId) q.set('type', this.sel.typeId);
                if (this.sel.checkIn) { q.set('in', this.sel.checkIn); q.set('out', this.sel.checkOut || this.sel.checkIn); }
                window.history.replaceState({}, '', `${window.location.pathname}?${q}`);
            },
        };
    }
</script>
<style>
    [x-cloak] { display: none !important; }
    .calm-hide-scroll { scrollbar-width: none; }
    .calm-hide-scroll::-webkit-scrollbar { display: none; }
    .calm-modal-panel { border-radius: 24px 24px 0 0; max-height: 92vh; height: auto; }
    @media (min-width: 640px) {
        .calm-modal-panel { border-radius: 28px; max-width: 580px; max-height: 86vh; height: fit-content; }
    }
</style>
@endsection
