{{-- Alpine-bound place card — include INSIDE a `<template x-for="p in items">`
     whose component spreads calmGrid() (partials/_web_grid_js provides
     cardTitle/cardMeta/fmtPrice/toggleLike). Visual twin of
     partials/_web_place_card — keep them in sync. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp
<a :href="`/places/${p.id}`" class="group block">
    <div class="relative overflow-hidden bg-[#f3f4f6]" style="border-radius: 20px; aspect-ratio: 20 / 19;">
        <template x-if="p.cover_photo_url">
            <img :src="p.cover_photo_url" :alt="cardTitle(p)" loading="lazy"
                 class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]">
        </template>
        <button type="button" @click.stop.prevent="toggleLike(p)"
                aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}"
                class="absolute flex items-center justify-center" style="top: 10px; inset-inline-end: 10px; width: 32px; height: 32px;">
            <svg width="24" height="24" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"
                 :fill="p.is_liked ? '#F88379' : 'rgba(0,0,0,0.45)'">
                <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
            </svg>
        </button>
    </div>
    <div style="padding: 10px 4px 0;">
        <div class="flex items-start justify-between" style="gap: 8px;">
            <h3 class="font-bold text-[#222] text-[15px] truncate {{ $fa }}" x-text="cardTitle(p)"></h3>
            <span class="shrink-0 text-[13px] text-[#222] tabular-nums {{ $fa }}"
                  x-text="p.rating && p.rating.avg ? '★ ' + p.rating.avg : '★ {{ $isRtl ? 'جديد' : 'New' }}'"></span>
        </div>
        <p class="text-[13px] text-[#717171] truncate {{ $fa }}" style="margin-top: 2px;" x-text="cardMeta(p)"></p>
        <p class="text-[14px] {{ $fa }}" style="margin-top: 6px;">
            <span class="font-bold text-[#222] tabular-nums" x-text="fmtPrice(p.price) + ' {{ $isRtl ? 'ر.س' : 'SAR' }}'"></span>
            <span class="text-[#717171] text-[13px]">/ {{ $isRtl ? 'الليلة' : 'night' }}</span>
        </p>
    </div>
</a>
