{{-- Alpine-bound full listing card (mobile-app parity: results, favorites) —
     include INSIDE a `<template x-for="p in items">` whose component spreads
     calmGrid(). Square image radius 24, then: title + ★ rating, description
     snippet, «العمارية · N ضيوف», «SR 7,700» + optional «لـ N أيام».
     STRUCTURE RULE: the heart button is a positioned SIBLING of the card
     link — a button nested inside an <a> is invalid HTML and the parser
     splits the card apart (images vanish). --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp
<div class="calm-press-card group relative">
    <a :href="`/places/${p.id}`" class="block">
        <div class="relative overflow-hidden" style="background-color: #F3F4F6; border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; aspect-ratio: 1;"
             x-data="{ ci: 0, imgs() { const l = (p.carousel_photos && p.carousel_photos.length ? p.carousel_photos : [p.cover_photo_url]).filter(Boolean); return l; } }">
            {{-- Photo carousel — scroll-snap over the backend-capped set (≤10) --}}
            <div class="flex overflow-x-auto calm-hide-scroll w-full h-full" style="scroll-snap-type: x mandatory; cursor: grab;"
                 x-init="window.calmDragScroll && calmDragScroll($el)"
                 @scroll="ci = Math.min(imgs().length - 1, Math.round(Math.abs($el.scrollLeft) / $el.clientWidth))">
                <template x-for="(u, ui) in imgs()" :key="ui">
                    <img :src="u" :alt="cardTitle(p)" loading="lazy"
                         class="w-full h-full object-cover shrink-0"
                         style="scroll-snap-align: center; scroll-snap-stop: always;">
                </template>
            </div>
            {{-- Pagination dots (app spec: active 14px, inactive 5px @55%) --}}
            <div class="absolute flex items-center justify-center" style="bottom: 10px; left: 0; right: 0; gap: 4px; pointer-events: none;"
                 x-show="imgs().length > 1">
                <template x-for="(u, di) in imgs()" :key="'d' + di">
                    {{-- Full style lives in the binding — Alpine string :style replaces the static attr --}}
                    <span class="calm-round"
                          :style="'height: 5px; border-radius: 3px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.25); transition: all 0.25s; ' + (di === ci ? 'width: 14px; opacity: 1;' : 'width: 5px; opacity: 0.55;')"></span>
                </template>
            </div>
        </div>
        <div style="padding-top: 12px; display: flex; flex-direction: column; gap: 4px;">
            <div class="flex items-center justify-between" style="gap: 8px;">
                <h3 class="font-bold text-black truncate {{ $fa }}" style="font-size: 16px; line-height: 21px;" x-text="cardTitle(p)"></h3>
                <span class="shrink-0 tabular-nums text-black" style="font-size: 13px; line-height: 18px;">
                    <span>★</span>
                    <span class="font-bold" x-text="p.rating && p.rating.avg ? p.rating.avg : '0.0'"></span>
                    <span style="color: #AAAAAA;" x-text="'(' + (p.rating ? p.rating.count : 0) + ')'"></span>
                </span>
            </div>
            <p class="truncate {{ $fa }}" x-show="cardDesc(p)" style="font-size: 13px; line-height: 18px; color: #AAAAAA;" x-text="cardDesc(p)"></p>
            <p class="truncate {{ $fa }}" style="font-size: 13px; line-height: 18px; color: #AAAAAA;" x-text="cardMeta(p)"></p>
            <p class="font-bold text-black tabular-nums" style="font-size: 15px; line-height: 20px; margin-top: 2px;"><bdi dir="ltr" x-text="priceSR(p)"></bdi></p>
            <p class="{{ $fa }}" x-show="stayLabel()" x-cloak style="font-size: 12px; line-height: 16px; color: #AAAAAA;" x-text="stayLabel()"></p>
        </div>
    </a>

    {{-- Heart — sibling overlay at inline-start, NOT inside the link --}}
    <button type="button" @click.stop.prevent="toggleLike(p)"
            aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}"
            class="calm-press-like absolute flex items-center justify-center"
            style="top: 14px; inset-inline-start: 14px; width: 32px; height: 32px;">
        <svg width="26" height="26" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"
             :fill="p.is_liked ? '#F88379' : 'rgba(0,0,0,0.2)'"
             style="filter: drop-shadow(0 1px 4px rgba(0,0,0,0.25));">
            <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
        </svg>
    </button>
</div>
