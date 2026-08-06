@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', $isRtl ? 'كالم — أماكن مختارة بعناية' : 'Calm — hand-picked stays')

@section('meta')
    <meta name="description" content="{{ __('brand_meta_description') }}">
    <meta property="og:title" content="{{ $isRtl ? 'كالم — أماكن مختارة بعناية' : 'Calm — hand-picked stays' }}">
    <meta property="og:description" content="{{ __('brand_meta_description') }}">
    <meta property="og:type" content="website">
@endsection

@section('body')
<div class="min-h-screen bg-white" x-data="calmHome()" x-init="init()"
     x-on:calm-search-apply.window="onApply($event.detail)">

    @include('partials._web_topbar', ['searchPill' => true])

    <main class="mx-auto w-full" style="max-width: 1240px; padding: 40px 24px 110px;">

        {{-- ══ Browse mode: curated lists (Figma reference) ══ --}}
        <div x-show="mode === 'browse'">
            @foreach($lists as $li => $list)
                <section class="calm-enter" style="{{ $li > 0 ? 'margin-top: 56px;' : '' }} animation-delay: {{ 140 + $li * 120 }}ms;"
                         aria-label="{{ $isRtl ? $list->name_ar : $list->name_en }}">
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 22px; line-height: 28px;">
                        {{ $isRtl ? $list->name_ar : $list->name_en }}
                    </h2>
                    @if($isRtl ? $list->description_ar : $list->description_en)
                        <p class="{{ $fa }}" style="font-size: 13px; margin-top: 4px; color: #AAAAAA;">{{ $isRtl ? $list->description_ar : $list->description_en }}</p>
                    @endif
                    <div class="flex overflow-x-auto calm-hide-scroll" style="gap: 24px; margin-top: 24px; padding: 4px 4px 8px;">
                        @foreach($list->places as $p)
                            @include('partials._web_place_card', ['p' => $p, 'compact' => true])
                        @endforeach

                        {{-- «عرض الكل» tile --}}
                        @php $seeAllCovers = $list->places->map(fn ($sp) => $sp->coverPhoto?->url ?? $sp->visiblePhotos()->first()?->url)->filter()->take(2)->values(); @endphp
                        <button type="button" @click="$dispatch('calm-open-search')"
                                class="calm-press-card shrink-0 flex flex-col items-center justify-center bg-white"
                                style="width: 190px; height: 190px; border-radius: 20px; gap: 16px; box-shadow: 0 0 50px rgba(0,0,0,0.05);">
                            <span class="relative block" style="width: 74px; height: 62px;">
                                @foreach($seeAllCovers as $ci => $cUrl)
                                    <img src="{{ $cUrl }}" alt="" loading="lazy"
                                         class="absolute object-cover border-2 border-white"
                                         style="width: 54px; height: 54px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                                                {{ $ci === 0 ? 'top: 8px; inset-inline-start: 0; transform: rotate(-7deg); z-index: 1;' : 'top: 0; inset-inline-end: 0; transform: rotate(6deg); z-index: 2;' }}">
                                @endforeach
                            </span>
                            <span class="flex items-center text-[13px] font-bold text-black {{ $fa }}" style="gap: 4px;">
                                <span>{{ $isRtl ? 'عرض الكل' : 'See all' }}</span>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
                                     style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                    <path d="M9 5l7 7-7 7"></path>
                                </svg>
                            </span>
                        </button>
                    </div>
                </section>
            @endforeach

            @if($lists->isEmpty())
                <div class="text-center {{ $fa }}" style="padding: 70px 0; color: #AAAAAA;">
                    {{ $isRtl ? 'لا توجد أماكن معروضة بعد — عد قريباً.' : 'Nothing to show yet — check back soon.' }}
                </div>
            @endif
        </div>

        {{-- ══ Results mode: 3-column grid + load more ══ --}}
        <div x-show="mode === 'results'" x-cloak>
            <div class="flex items-center justify-between flex-wrap" style="gap: 10px;">
                <div>
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 22px; line-height: 28px;">{{ $isRtl ? 'نتائج البحث' : 'Search results' }}</h2>
                    <p class="text-[13px] tabular-nums {{ $fa }}" style="color: #AAAAAA;" x-show="!loadingGrid || items.length > 0"
                       x-text="total + ' {{ $isRtl ? 'مكان' : 'places' }}'"></p>
                </div>
                <div class="flex items-center" style="gap: 8px;">
                    <button type="button" @click="$dispatch('calm-open-search')"
                            class="calm-press text-[13px] font-bold text-black bg-white transition-all {{ $fa }}"
                            style="padding: 11px 20px; border-radius: 999px; box-shadow: 0 0 50px rgba(0,0,0,0.08);">
                        {{ $isRtl ? 'تعديل البحث' : 'Edit search' }}
                    </button>
                    <button type="button" @click="clearSearch()"
                            class="text-[13px] font-semibold hover:text-black transition-colors {{ $fa }}"
                            style="padding: 10px 12px; color: #AAAAAA;">
                        ✕ {{ $isRtl ? 'مسح' : 'Clear' }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap: 28px 24px; margin-top: 24px;">
                <template x-for="p in items" :key="p.id">
                    @include('partials._web_card_template')
                </template>
            </div>

            {{-- First-page loading — skeleton cards --}}
            <div x-show="loadingGrid && items.length === 0"
                 class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap: 28px 24px; margin-top: 24px;">
                @for($sk = 0; $sk < 3; $sk++)
                    <div>
                        <div class="calm-skeleton" style="border-radius: 14px; aspect-ratio: 1.15;"></div>
                        <div class="calm-skeleton" style="height: 14px; border-radius: 4px; width: 72%; margin-top: 12px;"></div>
                        <div class="calm-skeleton" style="height: 14px; border-radius: 4px; width: 50%; margin-top: 8px;"></div>
                        <div class="calm-skeleton" style="height: 14px; border-radius: 4px; width: 40%; margin-top: 8px;"></div>
                    </div>
                @endfor
            </div>

            {{-- Empty / error states --}}
            <div x-show="!loadingGrid && !gridError && items.length === 0" x-cloak
                 class="text-center {{ $fa }}" style="padding: 70px 0; color: #AAAAAA;">
                <div style="font-size: 34px; margin-bottom: 10px;">🔍</div>
                {{ $isRtl ? 'لا توجد نتائج مطابقة — جرّب تعديل البحث.' : 'No matching places — try adjusting your search.' }}
            </div>
            <div x-show="gridError" x-cloak class="text-center text-[#DC2626] {{ $fa }}" style="padding: 50px 0;">
                {{ $isRtl ? 'حدث خطأ أثناء البحث — أعد المحاولة.' : 'Something went wrong — please retry.' }}
            </div>

            {{-- Load more --}}
            <div class="text-center" style="margin-top: 32px;" x-show="hasMore && !gridError" x-cloak>
                <button type="button" @click="loadMore(searchParams())" :disabled="loadingGrid"
                        class="calm-press inline-flex items-center font-bold text-white hover:opacity-90 disabled:opacity-60 transition-opacity {{ $fa }}"
                        style="padding: 13px 34px; border-radius: 12px; gap: 8px; font-size: 14px; background-color: #000;">
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

    @include('partials._web_search_modal', ['searchModalRedirect' => false])
    @include('partials._web_floating_nav')
    @include('partials._web_footer')
</div>

@include('partials._web_grid_js')
<script>
    function calmHome() {
        return {
            ...calmGrid('/api/places/search'),

            mode: 'browse',
            // The last selection the search modal applied — drives load-more
            // params and the shareable URL.
            applied: null,

            init() {
                const q = new URLSearchParams(window.location.search);
                if (q.get('city')) {
                    this.applied = {
                        cityId: q.get('city'),
                        areaId: q.get('area') || null,
                        typeId: q.get('type') || null,
                        checkIn: /^\d{4}-\d{2}-\d{2}$/.test(q.get('in') || '') ? q.get('in') : null,
                        checkOut: /^\d{4}-\d{2}-\d{2}$/.test(q.get('out') || '') ? q.get('out') : null,
                    };
                    this.mode = 'results';
                    this.fetchPage(1, this.searchParams());
                }
            },

            onApply(selection) {
                this.applied = selection;
                this.mode = 'results';
                this.fetchPage(1, this.searchParams());
                this.syncUrl();
            },

            searchParams() {
                const a = this.applied || {};
                const q = { city_id: a.cityId };
                if (a.areaId) q.city_area_id = a.areaId;
                if (a.typeId) q['place_type_ids[]'] = a.typeId;
                if (a.checkIn) { q.check_in = a.checkIn; q.check_out = a.checkOut || a.checkIn; }
                return q;
            },

            clearSearch() {
                this.applied = null;
                this.items = []; this.total = 0; this.hasMore = false;
                this.mode = 'browse';
                window.history.replaceState({}, '', window.location.pathname);
                this.$dispatch('calm-search-reset');
            },

            syncUrl() {
                const a = this.applied;
                const q = new URLSearchParams();
                q.set('city', a.cityId);
                if (a.areaId) q.set('area', a.areaId);
                if (a.typeId) q.set('type', a.typeId);
                if (a.checkIn) { q.set('in', a.checkIn); q.set('out', a.checkOut || a.checkIn); }
                window.history.replaceState({}, '', `${window.location.pathname}?${q}`);
            },
        };
    }
</script>
<style>
    [x-cloak] { display: none !important; }
    .calm-hide-scroll { scrollbar-width: none; }
    .calm-hide-scroll::-webkit-scrollbar { display: none; }
</style>
@endsection
