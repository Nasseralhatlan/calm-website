@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
    $isHost = $me?->isHost() ?? false;
@endphp

@section('title', $isRtl ? 'كالم — أماكن مختارة بعناية' : 'Calm — hand-picked stays')

@section('meta')
    <meta name="description" content="{{ __('brand_meta_description') }}">
    <meta property="og:title" content="{{ $isRtl ? 'كالم — أماكن مختارة بعناية' : 'Calm — hand-picked stays' }}">
    <meta property="og:description" content="{{ __('brand_meta_description') }}">
    <meta property="og:type" content="website">
@endsection

@section('body')
{{-- No x-init="init()": Alpine already auto-runs a component's init() method,
     and having both ran it twice — double search fetch, double analytics event. --}}
<div class="min-h-screen bg-white" x-data="calmHome()"
     x-on:calm-search-apply.window="onApply($event.detail)"
     x-on:calm-filters-apply.window="onFiltersApply($event.detail)"
     x-on:calm-search-state.window="searchState = $event.detail">

    {{-- Desktop site header (≥1024px) — shared with the results and place pages --}}
    @include('partials._web_desktop_header', ['searchOpensModal' => true])

    {{-- ══ App header: centered logo + «ابدء البحث» bar (sticky, blurred) ══ --}}
    <header class="calm-hide-desktop sticky top-0 z-30" x-show="mode === 'browse'"
            style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="mx-auto" style="max-width: 720px; padding: 14px 20px 14px;">
            <div class="flex items-center justify-center">
                <a href="{{ route('landing') }}"><img src="/assets/logo/logo.png" alt="Calm" style="height: 32px; width: auto;" draggable="false"></a>
            </div>
            <button type="button" @click="$dispatch('calm-open-search')"
                    class="calm-press calm-round flex items-center justify-center bg-white w-full"
                    style="margin-top: 14px; height: 54px; border-radius: 9999px; overflow: hidden; border: 1px solid #F1F1F1; box-shadow: 0 0 50px rgba(0,0,0,0.05); gap: 9px;">
                <span dir="ltr" class="flex items-center" style="gap: 9px;">
                    <svg width="16" height="16" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.125 13.125L11.25 11.25" stroke="black" stroke-width="1.5" stroke-linecap="round"/>
                        <ellipse cx="6.875" cy="7.49997" rx="5.625" ry="5.62497" stroke="black" stroke-width="1.5"/>
                    </svg>
                    <span class="text-[14px] font-semibold text-black {{ $fa }}">{{ $isRtl ? 'ابدء البحث' : 'Start searching' }}</span>
                </span>
            </button>
        </div>
    </header>

    <main class="mx-auto w-full calm-home-main" style="padding: 10px 0 36px;">

        {{-- ══ Browse mode ══ --}}
        <div x-show="mode === 'browse'">
            <div class="flex items-center justify-between" style="margin-top: 18px; gap: 10px; padding-inline: 20px;">
                <h1 class="font-bold text-black text-[22px] sm:text-[26px] {{ $fa }}" style="line-height: 1.25;">{{ $isRtl ? 'اهـلا بك' : 'Welcome' }}</h1>
                @if($isHost)
                    <a href="{{ route('user.places') }}"
                       class="calm-press lg:hidden inline-flex items-center text-white font-bold {{ $fa }}"
                       style="padding: 10px 16px; border-radius: 999px; gap: 6px; font-size: 12px; background-color: rgba(30,30,30,0.9);">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 16l-4-4 4-4M17 8l4 4-4 4"></path>
                        </svg>
                        {{ $isRtl ? 'وضع المضيف' : 'Host mode' }}
                    </a>
                @endif
            </div>

            {{-- Type boxes (app: 3 white squares) — hidden on desktop --}}
            <div class="grid grid-cols-3 lg:hidden" style="gap: 14px; margin-top: 22px; padding-inline: 20px;">
                @foreach($placeTypes->take(3) as $i => $t)
                    <button type="button" @click="$dispatch('calm-open-search', { typeId: @js($t->id) })"
                            class="calm-enter calm-press flex flex-col items-center justify-center bg-white min-w-0"
                            style="aspect-ratio: 1 / 0.92; border-radius: 26px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 10px; padding: 10px; box-shadow: 0 0 50px rgba(0,0,0,0.06); animation-delay: {{ 140 + $i * 50 }}ms;">
                        <span class="text-[26px] sm:text-[34px]" style="line-height: 1;">{{ $t->icon ?: '🏠' }}</span>
                        <span class="text-[12px] sm:text-[13px] font-bold text-black truncate w-full text-center {{ $fa }}">{{ $isRtl ? $t->name_ar : $t->name_en }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Curated lists — app rows: «{name} {icon}» + chevron circle --}}
            @foreach($lists as $li => $list)
                <section class="calm-enter" style="margin-top: 40px; animation-delay: {{ 260 + $li * 120 }}ms;"
                         aria-label="{{ $isRtl ? $list->name_ar : $list->name_en }}">
                    <div class="flex items-center justify-between" style="gap: 10px; padding-inline: 20px;">
                        <h2 class="font-bold text-black truncate text-[17px] sm:text-[19px] {{ $fa }}" style="line-height: 1.3;">
                            {{ $isRtl ? $list->name_ar : $list->name_en }}@if($list->icon)&nbsp;{{ $list->icon }}@endif
                        </h2>
                        {{-- See-all: chevron circle on mobile, text link on desktop --}}
                        <a href="{{ route('web.list', $list) }}" aria-label="{{ $isRtl ? 'عرض الكل' : 'See all' }}"
                           class="calm-press calm-round lg:hidden shrink-0 flex items-center justify-center text-black"
                           style="width: 36px; height: 36px; border-radius: 50%; background-color: #F5F5F5;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                                 style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                <path d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                        <a href="{{ route('web.list', $list) }}"
                           class="calm-press hidden lg:inline-flex items-center shrink-0 font-bold text-black hover:bg-[#F5F5F5] transition-colors {{ $fa }}"
                           style="gap: 5px; padding: 9px 16px; border-radius: 999px; font-size: 14px;">
                            <span>{{ $isRtl ? 'عرض الكل' : 'See all' }}</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
                                 style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                <path d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                    <div x-ref="list{{ $li }}" class="flex overflow-x-auto calm-hide-scroll lg:hidden" style="gap: 16px; margin-top: 4px; padding: 16px 20px 26px;">
                        @foreach($list->places->take(5) as $p)
                            @include('partials._web_place_card', ['p' => $p, 'compact' => true])
                        @endforeach

                        {{-- «عرض الكل» — animated stacked photos → the list page --}}
                        @php $seeAllCovers = $list->places->skip(5)->concat($list->places)->map(fn ($sp) => $sp->coverPhoto?->url ?? $sp->visiblePhotos()->first()?->url)->filter()->unique()->take(2)->values(); @endphp
                        <a href="{{ route('web.list', $list) }}"
                           x-data="{ shown: false }"
                           x-init="new IntersectionObserver((entries) => { shown = entries[0].isIntersecting; }, { root: $el.parentElement, threshold: 0.55 }).observe($el)"
                           :class="shown ? 'is-open' : ''"
                           class="calm-press calm-seeall shrink-0 flex flex-col items-center justify-center bg-white"
                           style="width: clamp(126px, 37vw, 172px); height: clamp(126px, 37vw, 172px); border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 14px; box-shadow: 0 0 50px rgba(0,0,0,0.08);">
                            <span class="relative block" style="width: 78px; height: 58px;">
                                @foreach($seeAllCovers as $ci => $cUrl)
                                    <img src="{{ $cUrl }}" alt="" loading="lazy"
                                         class="calm-seeall-img calm-seeall-img-{{ $ci }} object-cover">
                                @endforeach
                            </span>
                            <span class="flex items-center text-[13px] font-bold text-black {{ $fa }}" style="gap: 4px;">
                                <span>{{ $isRtl ? 'عرض الكل' : 'See all' }}</span>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
                                     style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                    <path d="M9 5l7 7-7 7"></path>
                                </svg>
                            </span>
                        </a>
                    </div>

                    {{-- Desktop: full grid — all items visible, none hidden in a
                         scroll — ending with the same animated «عرض الكل» deck
                         tile the mobile row uses. --}}
                    <div class="calm-home-grid">
                        @foreach($list->places->take(11) as $p)
                            @include('partials._web_place_card', ['p' => $p, 'compact' => false, 'cardRadius' => 32])
                        @endforeach

                        {{-- $seeAllCovers is built by the mobile row above (same
                             loop iteration) — reuse it, no extra photo lookups. --}}
                        <a href="{{ route('web.list', $list) }}"
                           x-data="{ shown: false }"
                           x-init="new IntersectionObserver((entries) => { shown = entries[0].isIntersecting; }, { threshold: 0.5 }).observe($el)"
                           :class="shown ? 'is-open' : ''"
                           class="calm-press calm-seeall flex flex-col items-center justify-center bg-white"
                           style="align-self: start; width: 100%; aspect-ratio: 1; border-radius: 32px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 14px; box-shadow: 0 0 50px rgba(0,0,0,0.08);">
                            <span class="relative block" style="width: 78px; height: 58px;">
                                @foreach($seeAllCovers as $ci => $cUrl)
                                    <img src="{{ $cUrl }}" alt="" loading="lazy"
                                         class="calm-seeall-img calm-seeall-img-{{ $ci }} object-cover">
                                @endforeach
                            </span>
                            <span class="flex items-center text-[13px] font-bold text-black {{ $fa }}" style="gap: 4px;">
                                <span>{{ $isRtl ? 'عرض الكل' : 'See all' }}</span>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
                                     style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}">
                                    <path d="M9 5l7 7-7 7"></path>
                                </svg>
                            </span>
                        </a>
                    </div>
                </section>
            @endforeach

            @if($lists->isEmpty())
                <div class="text-center {{ $fa }}" style="padding: 70px 20px; color: #AAAAAA;">
                    {{ $isRtl ? 'لا توجد أماكن معروضة بعد — عد قريباً.' : 'Nothing to show yet — check back soon.' }}
                </div>
            @endif
        </div>

        {{-- ══ Results mode — app: back + «أماكن فى {city} / N نتيجة» pill + filters ══ --}}
        <div x-show="mode === 'results'" x-cloak @scroll.window.passive="onResultsScroll()">
            {{-- App-style results bar — mobile/tablet only (desktop keeps the site header) --}}
            <div class="calm-hide-desktop sticky top-0 z-30 flex items-center justify-between"
                 style="gap: 10px; padding: 12px 20px; background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
                <button type="button" @click="clearSearch()" aria-label="{{ $isRtl ? 'رجوع' : 'Back' }}"
                        class="calm-press shrink-0 flex items-center justify-center text-black" style="width: 40px; height: 40px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                         style="{{ $isRtl ? '' : 'transform: scaleX(-1);' }}">
                        <path d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
                <button type="button" @click="$dispatch('calm-open-search')"
                        class="calm-press calm-round flex-1 min-w-0 bg-white text-center"
                        style="border-radius: 9999px; padding: 10px 20px; box-shadow: 0 0 50px rgba(0,0,0,0.08); max-width: 420px;">
                    <span class="block font-bold text-black truncate {{ $fa }}" style="font-size: 15px; line-height: 20px;"
                          x-text="'{{ $isRtl ? 'أماكن فى' : 'Places in' }} ' + ((searchState && searchState.cityName) || '…')"></span>
                    <span class="block tabular-nums {{ $fa }}" style="font-size: 12px; line-height: 16px; color: #AAAAAA;"
                          x-text="total + ' {{ $isRtl ? 'نتيجة' : 'results' }}'"></span>
                </button>
                <button type="button" @click="openFilters()" aria-label="{{ $isRtl ? 'الفلاتر' : 'Filters' }}"
                        class="calm-press shrink-0 flex items-center justify-center text-black" style="width: 40px; height: 40px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="4" y1="7" x2="20" y2="7"></line><circle cx="9" cy="7" r="2.4" fill="#fff"></circle>
                        <line x1="4" y1="16" x2="20" y2="16"></line><circle cx="15" cy="16" r="2.4" fill="#fff"></circle>
                    </svg>
                </button>
            </div>

            <div class="calm-results-wrap">

            {{-- ── Desktop filters sidebar — sticky beside the grid, same facets
                 and payload as the mobile «الفلاتر» sheet, applied on click. ── --}}
            <aside class="calm-results-filters" x-data="calmFiltersSidebar()"
                   x-effect="syncFrom(applied, filters)"
                   @resize.window.debounce.250ms="syncFrom(applied, filters)">
                <div class="flex items-center justify-between shrink-0" style="padding: 18px 20px 12px; border-bottom: 1px solid #F1F1F1;">
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 17px;">{{ $isRtl ? 'الفلاتر' : 'Filters' }}</h2>
                    <button type="button" @click="clearAll()" class="font-bold text-black underline {{ $fa }}" style="font-size: 13px;">{{ $isRtl ? 'مسح' : 'Clear' }}</button>
                </div>

                <div style="flex: 1 1 0%; min-height: 0; overflow-y: auto; padding: 4px 20px 18px;">
                    <div x-show="loading" class="text-center {{ $fa }}" style="padding: 40px 0; color: #AAAAAA;">…</div>

                    <template x-if="facets && !loading">
                        <div>
                            {{-- السعر / ليلة --}}
                            <div class="flex items-center justify-between" style="margin-top: 14px;">
                                <h3 class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'السعر / ليلة' : 'Price / night' }}</h3>
                            </div>
                            <div class="tabular-nums" dir="ltr" style="font-size: 12.5px; color: #AAAAAA; margin-top: 4px; {{ $isRtl ? 'text-align: right;' : '' }}"
                                 x-text="fmt(price[0]) + ' SR – ' + fmt(price[1]) + ' SR'"></div>
                            <div class="calm-dual-range" style="position: relative; height: 34px; margin-top: 6px;">
                                <div style="position: absolute; top: 15px; left: 0; right: 0; height: 4px; border-radius: 2px; background: #F1F1F1;"></div>
                                <div :style="'position: absolute; top: 15px; height: 4px; border-radius: 2px; background: #000; ' + trackStyle()"></div>
                                <input type="range" :min="facets.price.min" :max="facets.price.max" :step="priceStep()" x-model.number="price[0]"
                                       @input="if (price[0] > price[1]) price[0] = price[1]">
                                <input type="range" :min="facets.price.min" :max="facets.price.max" :step="priceStep()" x-model.number="price[1]"
                                       @input="if (price[1] < price[0]) price[1] = price[0]">
                            </div>

                            {{-- الضيوف --}}
                            <div class="flex items-center justify-between" style="margin-top: 22px;">
                                <div class="min-w-0">
                                    <h3 class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? 'الضيوف' : 'Guests' }}</h3>
                                    <p class="{{ $fa }}" style="font-size: 12.5px; color: #AAAAAA; margin-top: 2px;"
                                       x-text="guests ? guests + ' {{ $isRtl ? 'ضيوف' : 'guests' }}' : '{{ $isRtl ? 'أي عدد' : 'Any number' }}'"></p>
                                </div>
                                <div class="flex items-center shrink-0" style="gap: 8px;">
                                    <button type="button" @click="guests = Math.max(0, (guests || 0) - 1) || null"
                                            class="calm-press calm-round flex items-center justify-center text-black"
                                            style="width: 34px; height: 34px; border-radius: 50%; border: 1px solid #E9E9E9; font-size: 16px;">−</button>
                                    <button type="button" @click="guests = Math.min(facets.guests.max || 99, (guests || 0) + 1)"
                                            class="calm-press calm-round flex items-center justify-center text-black"
                                            style="width: 34px; height: 34px; border-radius: 50%; border: 1px solid #E9E9E9; font-size: 16px;">+</button>
                                </div>
                            </div>

                            {{-- نوع المكان --}}
                            <h3 class="font-bold text-black {{ $fa }}" style="font-size: 15px; margin-top: 22px;">{{ $isRtl ? 'نوع المكان' : 'Place type' }}</h3>
                            <div class="flex flex-wrap" style="gap: 8px; margin-top: 10px;">
                                <template x-for="t in facets.place_types" :key="t.id">
                                    <button type="button" @click="toggleIn(typeIds, t.id)"
                                            class="calm-press calm-round inline-flex items-center font-bold text-black {{ $fa }}"
                                            :style="'padding: 9px 14px; border-radius: 999px; gap: 5px; font-size: 12.5px; border: 1.5px solid ' + (typeIds.includes(t.id) ? '#000' : '#E9E9E9') + ';'">
                                        <span x-text="t.icon"></span>
                                        <span x-text="name(t) + ' (' + t.places_count + ')'"></span>
                                    </button>
                                </template>
                            </div>

                            {{-- المنطقة --}}
                            <template x-if="facets.areas.length">
                                <div>
                                    <h3 class="font-bold text-black {{ $fa }}" style="font-size: 15px; margin-top: 22px;">{{ $isRtl ? 'المنطقة' : 'Area' }}</h3>
                                    <div class="flex flex-wrap" style="gap: 8px; margin-top: 10px;">
                                        <template x-for="a in facets.areas" :key="a.id">
                                            <button type="button" @click="toggleIn(areaIds, a.id)"
                                                    class="calm-press calm-round font-bold text-black {{ $fa }}"
                                                    :style="'padding: 9px 14px; border-radius: 999px; font-size: 12.5px; border: 1.5px solid ' + (areaIds.includes(a.id) ? '#000' : '#E9E9E9') + ';'"
                                                    x-text="name(a) + ' (' + a.places_count + ')'"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Amenities, grouped --}}
                            <template x-for="g in facets.amenities" :key="g.group.id">
                                <div>
                                    <h3 class="font-bold text-black {{ $fa }}" style="font-size: 15px; margin-top: 22px;" x-text="name(g.group)"></h3>
                                    <div class="flex flex-wrap" style="gap: 8px; margin-top: 10px;">
                                        <template x-for="am in g.items" :key="am.id">
                                            <button type="button" @click="toggleIn(amenityIds, am.id)"
                                                    class="calm-press calm-round inline-flex items-center font-bold text-black {{ $fa }}"
                                                    :style="'padding: 9px 14px; border-radius: 999px; gap: 5px; font-size: 12.5px; border: 1.5px solid ' + (amenityIds.includes(am.id) ? '#000' : '#E9E9E9') + ';'">
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

                {{-- Apply --}}
                <div class="shrink-0" style="padding: 12px 20px 16px; border-top: 1px solid #F1F1F1;">
                    <button type="button" @click="apply()"
                            class="calm-press w-full font-bold text-white {{ $fa }}"
                            style="padding: 15px; border-radius: 16px; font-size: 14px; background-color: #000;">
                        {{ $isRtl ? 'تطبيق الفلاتر' : 'Apply filters' }}
                    </button>
                </div>
            </aside>

            <div class="calm-results-main">

            {{-- Desktop results heading --}}
            <div class="calm-results-head items-baseline justify-between" style="gap: 12px; padding-bottom: 4px;">
                <h1 class="font-bold text-black {{ $fa }}" style="font-size: 22px;"
                    x-text="'{{ $isRtl ? 'أماكن فى' : 'Places in' }} ' + ((searchState && searchState.cityName) || '…')"></h1>
                <span class="tabular-nums {{ $fa }}" style="font-size: 13px; color: #AAAAAA;"
                      x-text="total + ' {{ $isRtl ? 'نتيجة' : 'results' }}'"></span>
            </div>

            <div class="calm-results-grid" style="margin-top: 16px;">
                <template x-for="p in items" :key="p.id">
                    @include('partials._web_card_template')
                </template>
            </div>

            {{-- First-page loading — skeleton cards --}}
            <div x-show="loadingGrid && items.length === 0"
                 class="calm-results-grid" style="margin-top: 24px;">
                @for($sk = 0; $sk < 2; $sk++)
                    <div>
                        <div class="calm-skeleton" style="border-radius: 24px; aspect-ratio: 1;"></div>
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
                        style="padding: 13px 34px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 8px; font-size: 14px; background-color: #000;">
                    <svg x-show="loadingGrid" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <path d="M21 12a9 9 0 1 1-6.2-8.56">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.9s" repeatCount="indefinite"/>
                        </path>
                    </svg>
                    <span>{{ $isRtl ? 'عرض المزيد' : 'Load more' }}</span>
                </button>
            </div>
            {{-- end:results — they reached the bottom AND there are no more
                 pages to load, i.e. they ran out of options. Binary flag. --}}
            <div style="height: 1px;"
                 x-init="new IntersectionObserver((entries) => {
                     if (entries[0].isIntersecting && !hasMore && items.length > 0) {
                         window.calmTrackOnce?.('end', 'results');
                     }
                 }, { threshold: 0.1 }).observe($el)"></div>
            </div>{{-- /.calm-results-main --}}
            </div>{{-- /.calm-results-wrap --}}
        </div>
    </main>

    {{-- Floating «ابدء بحث جديد» (browse mode, like the app) — mobile/tablet only --}}
    <div class="calm-hide-desktop">
        <button type="button" x-show="mode === 'browse'" @click="$dispatch('calm-open-search')"
                class="calm-press calm-round fixed z-30 inline-flex items-center text-white font-bold {{ $fa }}"
                style="bottom: calc(84px + env(safe-area-inset-bottom, 0px)); left: 50%; transform: translateX(-50%); padding: 10px 18px; border-radius: 9999px; gap: 7px; font-size: 13px; background-color: rgba(30,30,30,0.55); backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px); box-shadow: 0 8px 22px rgba(0,0,0,0.18); white-space: nowrap;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="9"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            <span>{{ $isRtl ? 'ابدء بحث جديد' : 'Start a new search' }}</span>
        </button>
    </div>

    @include('partials._web_search_modal', ['searchModalRedirect' => false])
    @include('partials._web_filters_modal')
    {{-- App tab bar — mobile/tablet only; on desktop nav lives in the header dropdown --}}
    <div class="calm-hide-desktop" x-show="mode === 'browse'">
        @include('partials._web_floating_nav')
    </div>
    @include('partials._web_footer')
</div>

@include('partials._web_grid_js')
<script>
    /**
     * Desktop results sidebar — same facets, state shape and apply payload as
     * the mobile «الفلاتر» sheet (reuses calmFiltersModal), minus the modal
     * chrome: no open/close, no body scroll lock, applies in place.
     * Bookkeeping lives in closure vars, NOT reactive state, so the x-effect
     * that calls syncFrom() can't re-trigger itself.
     */
    function calmFiltersSidebar() {
        let loadedCity = null;
        let busy = false;
        const isDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

        return {
            ...window.calmFiltersModal(),

            async syncFrom(applied, parentFilters) {
                const cityId = applied && applied.cityId;
                if (!cityId || !isDesktop()) return;

                const typeIds = [...((applied && applied.typeIds) || [])];
                const areaIds = [...((applied && applied.areaIds) || [])];
                const f = parentFilters || {};

                // Same city → just mirror the parent's current selection.
                if (cityId === loadedCity) {
                    this.typeIds = typeIds;
                    this.areaIds = areaIds;
                    this.amenityIds = [...(f.amenityIds || [])];
                    this.guests = f.guests || null;
                    return;
                }
                if (busy) return;
                busy = true;
                loadedCity = cityId;
                this.cityId = cityId;
                this.typeIds = typeIds;
                this.areaIds = areaIds;
                this.amenityIds = [...(f.amenityIds || [])];
                this.guests = f.guests || null;
                this.loading = true;
                try {
                    const res = await fetch(`/api/places/filters?city_id=${cityId}`, { headers: { 'Accept': 'application/json' } });
                    const data = (await res.json()).data || null;
                    this.facets = data;
                    // Read bounds off the local copy — reading this.facets here
                    // would make the effect depend on what it just wrote.
                    if (data) {
                        this.price = [f.priceMin ?? data.price.min, f.priceMax ?? data.price.max];
                    }
                } catch (e) {
                    this.facets = null;
                    loadedCity = null;
                } finally {
                    this.loading = false;
                    busy = false;
                }
            },

            // No close()/scroll-lock — the panel stays put.
            apply() {
                if (!this.facets) return;
                this.$dispatch('calm-filters-apply', {
                    typeIds: [...this.typeIds],
                    areaIds: [...this.areaIds],
                    amenityIds: [...this.amenityIds],
                    guests: this.guests,
                    priceMin: this.price[0] > this.facets.price.min ? this.price[0] : null,
                    priceMax: this.price[1] < this.facets.price.max ? this.price[1] : null,
                });
            },
        };
    }

    function calmHome() {
        return {
            ...calmGrid('/api/places/search'),

            mode: 'browse',
            searchState: null,
            // The last selection the search modal applied — drives load-more
            // params and the shareable URL. areaIds/typeIds are arrays.
            applied: null,
            // Advanced filters from the الفلاتر sheet.
            filters: { priceMin: null, priceMax: null, guests: null, amenityIds: [] },

            init() {
                const q = new URLSearchParams(window.location.search);
                if (q.get('city')) {
                    const list = (v) => (v || '').split(',').filter(Boolean);
                    this.applied = {
                        cityId: q.get('city'),
                        areaIds: list(q.get('area')),
                        typeIds: list(q.get('type')),
                        checkIn: /^\d{4}-\d{2}-\d{2}$/.test(q.get('in') || '') ? q.get('in') : null,
                        checkOut: /^\d{4}-\d{2}-\d{2}$/.test(q.get('out') || '') ? q.get('out') : null,
                    };
                    this.mode = 'results';
                    this.fetchPage(1, this.searchParams()).then(() => this.trackResults());
                } else {
                    window.calmTrack?.('view', 'home');
                }
            },

            /**
             * view:results — fired once the fetch resolves, because
             * results_count (the single most important property we collect)
             * is only known then. See docs/feature-analytics.md.
             */
            /** scroll:results — binary flag, once per session (see the spec). */
            onResultsScroll() {
                if (this.mode !== 'results') return;
                if (window.scrollY > 200) window.calmTrackOnce?.('scroll', 'results');
            },

            trackResults() {
                const a = this.applied || {};
                window.calmTrack?.('view', 'results', {
                    city_id: a.cityId,
                    check_in: a.checkIn,
                    check_out: a.checkOut,
                    guests: this.filters?.guests,
                    results_count: this.total,
                });
            },

            onApply(selection) {
                this.applied = selection;
                // A fresh main search resets the advanced filters (app parity).
                this.filters = { priceMin: null, priceMax: null, guests: null, amenityIds: [] };
                this.mode = 'results';
                this.fetchPage(1, this.searchParams()).then(() => this.trackResults());
                this.syncUrl();
            },

            openFilters() {
                if (!this.applied) return;
                this.$dispatch('calm-open-filters', {
                    cityId: this.applied.cityId,
                    typeIds: this.applied.typeIds || [],
                    areaIds: this.applied.areaIds || [],
                    filters: this.filters,
                });
            },

            onFiltersApply(detail) {
                if (!this.applied) return;
                this.applied.typeIds = detail.typeIds;
                this.applied.areaIds = detail.areaIds;
                this.filters = {
                    priceMin: detail.priceMin, priceMax: detail.priceMax,
                    guests: detail.guests, amenityIds: detail.amenityIds,
                };
                // Keep the search sheet's chips in sync with the filter picks.
                this.$dispatch('calm-filters-sync', { typeIds: detail.typeIds, areaIds: detail.areaIds });
                this.fetchPage(1, this.searchParams()).then(() => this.trackResults());
                this.syncUrl();
            },

            searchParams() {
                const a = this.applied || {};
                const f = this.filters || {};
                const q = { city_id: a.cityId };
                // The API takes ONE area — send it only for a single pick.
                if ((a.areaIds || []).length === 1) q.city_area_id = a.areaIds[0];
                if ((a.typeIds || []).length) q['place_type_ids[]'] = a.typeIds;
                if (a.checkIn) { q.check_in = a.checkIn; q.check_out = a.checkOut || a.checkIn; }
                if (f.priceMin !== null && f.priceMin !== undefined) q.price_min = f.priceMin;
                if (f.priceMax !== null && f.priceMax !== undefined) q.price_max = f.priceMax;
                if (f.guests) q.guests = f.guests;
                if ((f.amenityIds || []).length) q['amenities[]'] = f.amenityIds;
                return q;
            },

            // «لـ 7 أيام» — inclusive day count like the app.
            stayLabel() {
                const a = this.applied;
                if (!a || !a.checkIn) return '';
                const days = Math.round((new Date(a.checkOut || a.checkIn) - new Date(a.checkIn)) / 86400000) + 1;
                return CALM_WEB.locale === 'ar' ? `لـ ${days} ${days === 1 ? 'يوم' : 'أيام'}` : `for ${days} ${days === 1 ? 'day' : 'days'}`;
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
                if ((a.areaIds || []).length) q.set('area', a.areaIds.join(','));
                if ((a.typeIds || []).length) q.set('type', a.typeIds.join(','));
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

    /* «عرض الكل» photos: stacked dead-center like a deck; when the tile
       scrolls into view at the row's end they deal out to each side. */
    .calm-seeall-img {
        position: absolute; top: 4px; left: 50%; width: 50px; height: 50px;
        margin-left: -25px; border: 3px solid #fff; border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.18);
        transition: transform 0.55s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .calm-seeall-img-0 { z-index: 1; }
    .calm-seeall-img-1 { z-index: 2; }
    .calm-seeall.is-open .calm-seeall-img-0 { transform: translate(-15px, 3px) rotate(-10deg); }
    .calm-seeall.is-open .calm-seeall-img-1 { transform: translate(15px, -3px) rotate(8deg); }
    @media (prefers-reduced-motion: reduce) {
        .calm-seeall-img { transition: none; }
        .calm-seeall.is-open .calm-seeall-img-0 { transform: translate(-15px, 3px) rotate(-10deg); }
        .calm-seeall.is-open .calm-seeall-img-1 { transform: translate(15px, -3px) rotate(8deg); }
    }

    /* ── Desktop-only home layout (≥1024px). Tablet + mobile keep the app view.
       These are self-contained display classes (not Tailwind lg:hidden) because
       Alpine's x-show writes an inline `display`, which beats a utility class —
       reverting to the stylesheet lets x-show and the breakpoint compose. ── */
    /* calm-show-desktop / calm-hide-desktop live in resources/css/app.css —
       the place page uses them too. */
    .calm-home-main { max-width: 720px; }
    .calm-home-grid { display: none; }
    @media (min-width: 1024px) {
        .calm-home-main { max-width: 1240px; }
        .calm-home-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
            gap: 34px 22px;
            padding: 20px 24px 8px;
        }
    }

    /* ── Search results ── mobile/tablet: 1 → 2 columns, no sidebar (app view).
       Desktop: sticky filters panel + 3-column grid. ── */
    .calm-results-main { padding-inline: 20px; }
    /* minmax(0, 1fr), never plain 1fr: each card holds a horizontal photo
       scroller, and a grid item's default min-width:auto would size the track
       to that scroller's min-content (columns blow out to ~1500px). */
    .calm-results-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 32px 24px; }
    .calm-results-filters { display: none; }
    .calm-results-head { display: none; }
    @media (min-width: 640px) {
        .calm-results-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 1024px) {
        .calm-results-wrap {
            display: flex;
            align-items: flex-start;
            gap: 26px;
            padding: 24px 24px 8px;
        }
        .calm-results-main { flex: 1 1 0%; min-width: 0; padding-inline: 0; }
        .calm-results-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 34px 24px; }
        /* Slightly shorter photos than the square used elsewhere. !important
           because the card partial sets aspect-ratio inline. */
        .calm-results-grid [role="link"] { aspect-ratio: 1.12 !important; }
        .calm-results-head { display: flex; }
        .calm-results-filters {
            display: flex;
            flex-direction: column;
            flex: 0 0 380px;
            width: 380px;
            position: sticky;
            /* Clears the sticky site header. */
            top: 104px;
            max-height: calc(100vh - 128px);
            background: #fff;
            border-radius: 24px;
            corner-shape: squircle;
            -webkit-corner-shape: squircle;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.07);
            overflow: hidden;
        }
    }
</style>
@endsection
