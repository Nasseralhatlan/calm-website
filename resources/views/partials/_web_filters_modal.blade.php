{{-- Filters sheet (app parity «الفلاتر») — built from
     GET /api/places/filters?city_id=: price range slider, guests stepper,
     type / area / amenity chips with live counts. Window events:

       open:  $dispatch('calm-open-filters', { cityId, typeIds, areaIds, filters })
       apply: emits 'calm-filters-apply' with
              { typeIds, areaIds, priceMin, priceMax, guests, amenityIds }
              (price only when narrowed inside the facet bounds) --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp
<div x-data="calmFiltersModal()"
     x-on:calm-open-filters.window="open($event.detail || {})"
     x-show="openState" x-cloak class="fixed inset-0 z-50 flex flex-col"
     style="background-color: rgba(250,250,250,0.8); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);"
     role="dialog" aria-modal="true">

    <div class="calm-filters-panel mx-auto w-full bg-white flex flex-col" style="max-width: 560px; flex: 1 1 0%; min-height: 0;">
        {{-- Header: X + الفلاتر + مسح --}}
        <div class="shrink-0 flex items-center justify-between" style="padding: 18px 22px 12px;">
            <button type="button" @click="close()" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                    class="calm-press calm-round flex items-center justify-center bg-white text-black"
                    style="width: 42px; height: 42px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 16px;">✕</button>
            <h2 class="font-bold text-black {{ $fa }}" style="font-size: 18px;">{{ $isRtl ? 'الفلاتر' : 'Filters' }}</h2>
            <button type="button" @click="clearAll()" class="font-bold text-black underline {{ $fa }}" style="font-size: 13px;">{{ $isRtl ? 'مسح' : 'Clear' }}</button>
        </div>

        <div style="flex: 1 1 0%; min-height: 0; overflow-y: auto; padding: 6px 22px 20px;">
            {{-- Loading --}}
            <div x-show="loading" class="text-center {{ $fa }}" style="padding: 50px 0; color: #AAAAAA;">…</div>

            <template x-if="facets && !loading">
                <div>
                    {{-- السعر / ليلة --}}
                    <div class="flex items-center justify-between" style="margin-top: 8px;">
                        <h3 class="font-bold text-black {{ $fa }}" style="font-size: 16px;">{{ $isRtl ? 'السعر / ليلة' : 'Price / night' }}</h3>
                        <span class="tabular-nums" dir="ltr" style="font-size: 13px; color: #AAAAAA;"
                              x-text="fmt(price[0]) + ' SR – ' + fmt(price[1]) + ' SR'"></span>
                    </div>
                    <div class="calm-dual-range" style="position: relative; height: 34px; margin-top: 8px;">
                        <div style="position: absolute; top: 15px; left: 0; right: 0; height: 4px; border-radius: 2px; background: #F1F1F1;"></div>
                        <div :style="'position: absolute; top: 15px; height: 4px; border-radius: 2px; background: #000; ' + trackStyle()"></div>
                        <input type="range" :min="facets.price.min" :max="facets.price.max" :step="priceStep()" x-model.number="price[0]"
                               @input="if (price[0] > price[1]) price[0] = price[1]">
                        <input type="range" :min="facets.price.min" :max="facets.price.max" :step="priceStep()" x-model.number="price[1]"
                               @input="if (price[1] < price[0]) price[1] = price[0]">
                    </div>

                    {{-- الضيوف --}}
                    <div class="flex items-center justify-between" style="margin-top: 26px;">
                        <div>
                            <h3 class="font-bold text-black {{ $fa }}" style="font-size: 16px;">{{ $isRtl ? 'الضيوف' : 'Guests' }}</h3>
                            <p class="{{ $fa }}" style="font-size: 13px; color: #AAAAAA; margin-top: 2px;"
                               x-text="guests ? guests + ' {{ $isRtl ? 'ضيوف' : 'guests' }}' : '{{ $isRtl ? 'أي عدد' : 'Any number' }}'"></p>
                        </div>
                        <div class="flex items-center" style="gap: 10px;">
                            <button type="button" @click="guests = Math.max(0, (guests || 0) - 1) || null"
                                    class="calm-press calm-round flex items-center justify-center text-black"
                                    style="width: 38px; height: 38px; border-radius: 50%; border: 1px solid #E9E9E9; font-size: 17px;">−</button>
                            <button type="button" @click="guests = Math.min(facets.guests.max || 99, (guests || 0) + 1)"
                                    class="calm-press calm-round flex items-center justify-center text-black"
                                    style="width: 38px; height: 38px; border-radius: 50%; border: 1px solid #E9E9E9; font-size: 17px;">+</button>
                        </div>
                    </div>

                    {{-- نوع المكان --}}
                    <h3 class="font-bold text-black {{ $fa }}" style="font-size: 16px; margin-top: 26px;">{{ $isRtl ? 'نوع المكان' : 'Place type' }}</h3>
                    <div class="flex flex-wrap" style="gap: 10px; margin-top: 12px;">
                        <template x-for="t in facets.place_types" :key="t.id">
                            <button type="button" @click="toggleIn(typeIds, t.id)"
                                    class="calm-press calm-round inline-flex items-center font-bold text-black {{ $fa }}"
                                    :style="'padding: 11px 18px; border-radius: 999px; gap: 6px; font-size: 13px; border: 1.5px solid ' + (typeIds.includes(t.id) ? '#000' : '#E9E9E9') + ';'">
                                <span x-text="t.icon"></span>
                                <span x-text="name(t) + ' (' + t.places_count + ')'"></span>
                            </button>
                        </template>
                    </div>

                    {{-- المنطقة --}}
                    <template x-if="facets.areas.length">
                        <div>
                            <h3 class="font-bold text-black {{ $fa }}" style="font-size: 16px; margin-top: 26px;">{{ $isRtl ? 'المنطقة' : 'Area' }}</h3>
                            <div class="flex flex-wrap" style="gap: 10px; margin-top: 12px;">
                                <template x-for="a in facets.areas" :key="a.id">
                                    <button type="button" @click="toggleIn(areaIds, a.id)"
                                            class="calm-press calm-round font-bold text-black {{ $fa }}"
                                            :style="'padding: 11px 18px; border-radius: 999px; font-size: 13px; border: 1.5px solid ' + (areaIds.includes(a.id) ? '#000' : '#E9E9E9') + ';'"
                                            x-text="name(a) + ' (' + a.places_count + ')'"></button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Amenities, grouped --}}
                    <template x-for="g in facets.amenities" :key="g.group.id">
                        <div>
                            <h3 class="font-bold text-black {{ $fa }}" style="font-size: 16px; margin-top: 26px;" x-text="name(g.group)"></h3>
                            <div class="flex flex-wrap" style="gap: 10px; margin-top: 12px;">
                                <template x-for="am in g.items" :key="am.id">
                                    <button type="button" @click="toggleIn(amenityIds, am.id)"
                                            class="calm-press calm-round inline-flex items-center font-bold text-black {{ $fa }}"
                                            :style="'padding: 11px 18px; border-radius: 999px; gap: 6px; font-size: 13px; border: 1.5px solid ' + (amenityIds.includes(am.id) ? '#000' : '#E9E9E9') + ';'">
                                        <span x-show="am.icon" x-text="am.icon"></span>
                                        <span x-text="name(am) + ' (' + am.places_count + ')'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="shrink-0" style="padding: 12px 22px calc(16px + env(safe-area-inset-bottom, 0px)); border-top: 1px solid #F1F1F1;">
            <button type="button" @click="apply()"
                    class="calm-press w-full font-bold text-white {{ $fa }}"
                    style="padding: 16px; border-radius: 18px; font-size: 15px; background-color: #000;">
                {{ $isRtl ? 'عرض النتائج' : 'Show results' }}
            </button>
        </div>
    </div>
</div>

<script>
    if (!window.calmFiltersModal) {
        window.calmFiltersModal = function () {
            const AR = @js($isRtl);

            return {
                openState: false,
                loading: false,
                facets: null,
                cityId: null,
                price: [0, 0],
                guests: null,
                typeIds: [],
                areaIds: [],
                amenityIds: [],

                async open(detail) {
                    this.cityId = detail.cityId || this.cityId;
                    if (!this.cityId) return;
                    this.typeIds = [...(detail.typeIds || [])];
                    this.areaIds = [...(detail.areaIds || [])];
                    const f = detail.filters || {};
                    this.guests = f.guests || null;
                    this.amenityIds = [...(f.amenityIds || [])];
                    this.openState = true;
                    window.calmTrack?.('filter', 'open');
                    document.body.style.overflow = 'hidden';

                    this.loading = true;
                    try {
                        const res = await fetch(`/api/places/filters?city_id=${this.cityId}`, { headers: { 'Accept': 'application/json' } });
                        this.facets = (await res.json()).data || null;
                        if (this.facets) {
                            this.price = [
                                f.priceMin ?? this.facets.price.min,
                                f.priceMax ?? this.facets.price.max,
                            ];
                        }
                    } catch (e) {
                        this.facets = null;
                    } finally {
                        this.loading = false;
                    }
                },
                close() {
                    // apply() closes too and has already sent filter:apply —
                    // don't also count that as an abandon.
                    if (this.openState && !this._applied) window.calmTrack?.('filter', 'close');
                    this._applied = false;
                    this.openState = false;
                    document.body.style.overflow = '';
                },
                clearAll() {
                    if (!this.facets) return;
                    this.price = [this.facets.price.min, this.facets.price.max];
                    this.guests = null;
                    this.typeIds = [];
                    this.areaIds = [];
                    this.amenityIds = [];
                },
                apply() {
                    if (!this.facets) { this.close(); return; }

                    const priceMin = this.price[0] > this.facets.price.min ? this.price[0] : null;
                    const priceMax = this.price[1] < this.facets.price.max ? this.price[1] : null;
                    window.calmTrack?.('filter', 'apply', {
                        city_id: this.cityId,
                        place_type_ids: [...this.typeIds],
                        city_area_ids: [...this.areaIds],
                        amenity_ids: [...this.amenityIds],
                        price_min: priceMin,
                        price_max: priceMax,
                        guests: this.guests,
                    });

                    this._applied = true; // suppress filter:close — see close()
                    this.close();
                    this.$dispatch('calm-filters-apply', {
                        typeIds: [...this.typeIds],
                        areaIds: [...this.areaIds],
                        amenityIds: [...this.amenityIds],
                        guests: this.guests,
                        // Only send price when narrowed inside the bounds.
                        priceMin: this.price[0] > this.facets.price.min ? this.price[0] : null,
                        priceMax: this.price[1] < this.facets.price.max ? this.price[1] : null,
                    });
                },

                toggleIn(list, id) {
                    const i = list.indexOf(id);
                    if (i >= 0) list.splice(i, 1); else list.push(id);
                },
                name(o) { return (AR ? o.name_ar : o.name_en) || o.name_ar || o.name_en || ''; },
                fmt(v) { return Number(v || 0).toLocaleString('en-US'); },
                priceStep() {
                    const span = (this.facets?.price.max || 0) - (this.facets?.price.min || 0);
                    return span > 5000 ? 100 : (span > 1000 ? 50 : 10);
                },
                trackStyle() {
                    const f = this.facets; if (!f || f.price.max === f.price.min) return 'left: 0; right: 0;';
                    const lo = (this.price[0] - f.price.min) / (f.price.max - f.price.min) * 100;
                    const hi = (this.price[1] - f.price.min) / (f.price.max - f.price.min) * 100;
                    const rtl = document.documentElement.dir === 'rtl';
                    return rtl
                        ? `right: ${lo}%; left: ${100 - hi}%;`
                        : `left: ${lo}%; right: ${100 - hi}%;`;
                },
            };
        };
    }
</script>
<style>
    /* Dual-range slider: two stacked native inputs; only the thumbs catch events. */
    .calm-dual-range input[type="range"] {
        position: absolute; top: 0; left: 0; width: 100%; height: 34px; margin: 0;
        -webkit-appearance: none; appearance: none; background: transparent; pointer-events: none;
    }
    .calm-dual-range input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none; appearance: none; pointer-events: auto;
        width: 26px; height: 26px; border-radius: 50%; background: #fff;
        border: 1.5px solid #E0E0E0; box-shadow: 0 2px 8px rgba(0,0,0,0.15); cursor: pointer; margin-top: 4px;
    }
    .calm-dual-range input[type="range"]::-moz-range-thumb {
        pointer-events: auto; width: 24px; height: 24px; border-radius: 50%; background: #fff;
        border: 1.5px solid #E0E0E0; box-shadow: 0 2px 8px rgba(0,0,0,0.15); cursor: pointer;
    }
</style>
<style>
    .calm-filters-panel {
        margin-top: 14px;
        border-radius: 28px 28px 0 0;
        box-shadow: 0 -4px 16px rgba(0,0,0,0.12);
        animation: calm-sheet-up 0.38s cubic-bezier(0.22, 0.9, 0.36, 1);
    }
    @media (min-width: 640px) {
        .calm-filters-panel {
            margin: auto 0;
            align-self: center;
            max-height: 86vh;
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.25);
            animation: calm-modal-in 0.3s ease-out;
        }
    }
    @keyframes calm-modal-in { from { opacity: 0; transform: scale(0.96) translateY(12px); } to { opacity: 1; transform: none; } }
</style>
