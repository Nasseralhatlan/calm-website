{{-- Alpine-bound grid place card (Figma reference tokens) — include INSIDE a
     `<template x-for="p in items">` whose component spreads calmGrid()
     (cardTitle/cardMeta/fmtPrice/toggleLike). Visual twin of
     partials/_web_place_card — keep them in sync.
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
        <div class="relative overflow-hidden" style="background-color: #F3F4F6; border-radius: 18px; corner-shape: squircle; -webkit-corner-shape: squircle; aspect-ratio: 1.15;">
            <template x-if="p.cover_photo_url">
                <img :src="p.cover_photo_url" :alt="cardTitle(p)" loading="lazy"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]">
            </template>
        </div>
        <div style="padding-top: 12px; display: flex; flex-direction: column; gap: 4px;">
            <div class="flex items-center justify-between" style="gap: 8px;">
                <h3 class="font-bold text-black truncate {{ $fa }}" style="font-size: 15px; line-height: 20px;" x-text="cardTitle(p)"></h3>
                <span class="shrink-0 tabular-nums text-black" style="font-size: 13px; line-height: 18px;">
                    <span>★</span>
                    <span class="font-bold {{ $fa }}" x-text="p.rating && p.rating.avg ? p.rating.avg : '{{ $isRtl ? 'جديد' : 'New' }}'"></span>
                    <span style="color: #AAAAAA;" x-show="p.rating && p.rating.avg" x-text="'(' + (p.rating ? p.rating.count : 0) + ')'"></span>
                </span>
            </div>
            <p class="truncate {{ $fa }}" style="font-size: 13px; line-height: 18px; color: #AAAAAA;" x-text="cardMeta(p)"></p>
            <p class="font-bold text-black tabular-nums" style="font-size: 14px; line-height: 20px; margin-top: 2px;"><bdi dir="ltr" x-text="fmtPrice(p.price) + ' SR'"></bdi></p>
        </div>
    </a>

    {{-- Heart — sibling overlay, NOT inside the link --}}
    <button type="button" @click.stop.prevent="toggleLike(p)"
            aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}"
            class="calm-press-like absolute flex items-center justify-center"
            style="top: 12px; inset-inline-end: 12px; width: 32px; height: 32px;">
        <svg width="24" height="24" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"
             :fill="p.is_liked ? '#F88379' : 'transparent'"
             style="filter: drop-shadow(0 1px 4px rgba(0,0,0,0.35));">
            <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
        </svg>
    </button>
</div>
