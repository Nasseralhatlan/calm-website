{{-- Server-rendered place card — Figma reference style.
     $compact = true  → home carousels: 170×170 image, squircle radius 18,
                        then title (black bold) / «area . city» (#AAAAAA) /
                        «375 SR» (black bold, aligned with the text).
     $compact = false → grids: image aspect 1.15 squircle radius 18,
                        title + rating, muted meta, price.
     STRUCTURE RULE: the heart is a positioned SIBLING of the card link —
     an <a>/<button> nested inside an <a> is invalid HTML and makes the
     browser parser split the card apart (images vanish).
     Expects $p eager-loaded via PlaceService::eagerHomeFields(). Keep in sync
     with partials/_web_card_template (the Alpine twin for JS grids). --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
    $compact = $compact ?? false;

    $cover = $p->coverPhoto?->url ?? $p->visiblePhotos()->first()?->url;
    $cardCity = $p->cityArea?->city;
    $areaName = $isRtl ? $p->cityArea?->name_ar : $p->cityArea?->name_en;
    $cityName = $cardCity ? ($isRtl ? $cardCity->name_ar : $cardCity->name_en) : null;
    $subtitle = collect([$areaName, $cityName])->filter()->implode(' . ');
    $ratingAvg = $p->published_reviews_avg_rate !== null ? number_format((float) $p->published_reviews_avg_rate, 1) : null;
    $ratingCount = (int) ($p->published_reviews_count ?? 0);
    $liked = (bool) ($p->liked_by_me ?? false);
    $heartSize = $compact ? 30 : 32;
    $priceLabel = number_format((int) $p->price).' SR';
@endphp
<div class="calm-press-card group relative shrink-0" @if($compact) style="width: 170px;" @endif>
    <a href="{{ route('places.show', $p) }}" class="block">
        <div class="relative overflow-hidden"
             style="background-color: #F3F4F6; border-radius: 18px; corner-shape: squircle; -webkit-corner-shape: squircle; {{ $compact ? 'width: 170px; height: 170px;' : 'aspect-ratio: 1.15;' }}">
            @if($cover)
                <img src="{{ $cover }}" alt="{{ $p->localized_title }}" loading="lazy"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]">
            @endif
        </div>
        @if($compact)
            <div style="padding-top: 10px; display: flex; flex-direction: column; gap: 4px;">
                <span class="font-bold text-black truncate {{ $fa }}" style="font-size: 15px; line-height: 20px;">{{ $p->localized_title }}</span>
                @if($subtitle !== '')
                    <span class="truncate {{ $fa }}" style="font-size: 13px; line-height: 17px; color: #AAAAAA;">{{ $subtitle }}</span>
                @endif
                <span class="font-bold text-black tabular-nums" style="font-size: 14px; line-height: 18px;"><bdi dir="ltr">{{ $priceLabel }}</bdi></span>
            </div>
        @else
            <div style="padding-top: 12px; display: flex; flex-direction: column; gap: 4px;">
                <div class="flex items-center justify-between" style="gap: 8px;">
                    <h3 class="font-bold text-black truncate {{ $fa }}" style="font-size: 15px; line-height: 20px;">{{ $p->localized_title }}</h3>
                    <span class="shrink-0 tabular-nums text-black" style="font-size: 13px; line-height: 18px;">
                        <span style="font-size: 13px;">★</span>
                        @if($ratingAvg)
                            <span class="font-bold">{{ $ratingAvg }}</span>
                            <span style="color: #AAAAAA;">({{ $ratingCount }})</span>
                        @else
                            <span class="font-bold {{ $fa }}">{{ $isRtl ? 'جديد' : 'New' }}</span>
                        @endif
                    </span>
                </div>
                @if($subtitle !== '')
                    <p class="truncate {{ $fa }}" style="font-size: 13px; line-height: 18px; color: #AAAAAA;">{{ $subtitle }}</p>
                @endif
                <p class="font-bold text-black tabular-nums" style="font-size: 14px; line-height: 20px; margin-top: 2px;"><bdi dir="ltr">{{ $priceLabel }}</bdi></p>
            </div>
        @endif
    </a>

    {{-- Heart — sibling overlay, NOT inside the link --}}
    <div class="absolute" style="top: 12px; inset-inline-end: 12px;">
        @if($me)
            <button type="button" x-data="{ liked: @js($liked) }"
                    @click.stop.prevent="liked = !liked; fetch('/api/places/{{ $p->id }}/like', { method: liked ? 'POST' : 'DELETE', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })"
                    aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}"
                    class="calm-press-like flex items-center justify-center" style="width: {{ $heartSize }}px; height: {{ $heartSize }}px;">
                <svg width="{{ $heartSize - 8 }}" height="{{ $heartSize - 8 }}" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"
                     :fill="liked ? '#F88379' : 'transparent'" fill="transparent"
                     style="filter: drop-shadow(0 1px 4px rgba(0,0,0,0.35));">
                    <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
                </svg>
            </button>
        @else
            <a href="{{ route('login', ['next' => request()->getRequestUri()]) }}"
               aria-label="{{ $isRtl ? 'سجّل الدخول للحفظ في المفضلة' : 'Sign in to save' }}"
               class="calm-press-like flex items-center justify-center" style="width: {{ $heartSize }}px; height: {{ $heartSize }}px;">
                <svg width="{{ $heartSize - 8 }}" height="{{ $heartSize - 8 }}" viewBox="0 0 24 24" fill="transparent" stroke="#fff" stroke-width="2"
                     style="filter: drop-shadow(0 1px 4px rgba(0,0,0,0.35));">
                    <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
                </svg>
            </a>
        @endif
    </div>
</div>
