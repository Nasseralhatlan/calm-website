{{-- Server-rendered place card (curated list rows on the home page).
     Expects $p eager-loaded via PlaceService::eagerHomeFields(). Optional
     $cardWidth pins a fixed width inside horizontal scroll rows.
     Keep the visuals in sync with partials/_web_card_template (the Alpine
     twin used by the JS grids). --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();

    $cover = $p->coverPhoto?->url ?? $p->visiblePhotos()->first()?->url;
    $cardCity = $p->cityArea?->city;
    $meta = collect([
        $isRtl ? $p->type?->name_ar : $p->type?->name_en,
        $cardCity ? ($isRtl ? $cardCity->name_ar : $cardCity->name_en) : null,
        $isRtl ? $p->cityArea?->name_ar : $p->cityArea?->name_en,
    ])->filter()->implode(' · ');
    $ratingAvg = $p->published_reviews_avg_rate !== null ? number_format((float) $p->published_reviews_avg_rate, 1) : null;
    $liked = (bool) ($p->liked_by_me ?? false);
@endphp
<a href="{{ route('places.show', $p) }}" class="group block shrink-0" @isset($cardWidth) style="width: {{ $cardWidth }};" @endisset>
    <div class="relative overflow-hidden bg-[#f3f4f6]" style="border-radius: 20px; aspect-ratio: 20 / 19;">
        @if($cover)
            <img src="{{ $cover }}" alt="{{ $p->localized_title }}" loading="lazy"
                 class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]">
        @endif
        <div class="absolute" style="top: 10px; inset-inline-end: 10px;">
            @if($me)
                <button type="button" x-data="{ liked: @js($liked) }"
                        @click.stop.prevent="liked = !liked; fetch('/api/places/{{ $p->id }}/like', { method: liked ? 'POST' : 'DELETE', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })"
                        aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}"
                        class="flex items-center justify-center" style="width: 32px; height: 32px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"
                         :fill="liked ? '#F88379' : 'rgba(0,0,0,0.45)'" fill="rgba(0,0,0,0.45)">
                        <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
                    </svg>
                </button>
            @else
                <a href="{{ route('login', ['next' => request()->getRequestUri()]) }}" @click.stop
                   aria-label="{{ $isRtl ? 'سجّل الدخول للحفظ في المفضلة' : 'Sign in to save' }}"
                   class="flex items-center justify-center" style="width: 32px; height: 32px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="rgba(0,0,0,0.45)" stroke="#fff" stroke-width="1.8">
                        <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
                    </svg>
                </a>
            @endif
        </div>
    </div>
    <div style="padding: 10px 4px 0;">
        <div class="flex items-start justify-between" style="gap: 8px;">
            <h3 class="font-bold text-[#222] text-[15px] truncate {{ $fa }}">{{ $p->localized_title }}</h3>
            <span class="shrink-0 text-[13px] text-[#222] tabular-nums {{ $ratingAvg ? '' : $fa }}">
                ★ {{ $ratingAvg ?? ($isRtl ? 'جديد' : 'New') }}
            </span>
        </div>
        @if($meta !== '')
            <p class="text-[13px] text-[#717171] truncate {{ $fa }}" style="margin-top: 2px;">{{ $meta }}</p>
        @endif
        <p class="text-[14px] {{ $fa }}" style="margin-top: 6px;">
            <span class="font-bold text-[#222] tabular-nums">{{ number_format((int) $p->price) }} {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
            <span class="text-[#717171] text-[13px]">/ {{ $isRtl ? 'الليلة' : 'night' }}</span>
        </p>
    </div>
</a>
