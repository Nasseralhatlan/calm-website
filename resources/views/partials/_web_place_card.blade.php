{{-- Server-rendered place card, Airbnb-style.
     $compact = true  → compact square card (home carousels): 158×158 image,
                        radius 24, two 12/16 lines (title, price · rating).
     $compact = false → full/hero card (grids): image aspect 1.15, radius 24,
                        title 15/20 bold #000 + rating, meta 13/18 muted,
                        price 14/20 bold.
     «مفضل الضيوف» badge at inline-start when avg ≥ 4.8 with ≥ 2 reviews;
     heart at inline-end.
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
    $meta = collect([
        $isRtl ? $p->type?->name_ar : $p->type?->name_en,
        $cardCity ? ($isRtl ? $cardCity->name_ar : $cardCity->name_en) : null,
        $isRtl ? $p->cityArea?->name_ar : $p->cityArea?->name_en,
    ])->filter()->implode(' · ');
    $ratingAvg = $p->published_reviews_avg_rate !== null ? number_format((float) $p->published_reviews_avg_rate, 1) : null;
    $ratingCount = (int) ($p->published_reviews_count ?? 0);
    $guestFavorite = $ratingAvg !== null && (float) $ratingAvg >= 4.8 && $ratingCount >= 2;
    $liked = (bool) ($p->liked_by_me ?? false);
    $heartSize = $compact ? 30 : 32;
    $priceLabel = number_format((int) $p->price).' '.($isRtl ? 'ر.س' : 'SAR');
@endphp
<div class="calm-press-card group relative shrink-0" @if($compact) style="width: 158px;" @endif>
    <a href="{{ route('places.show', $p) }}" class="block">
        <div class="relative overflow-hidden bg-[#F3F4F6]"
             style="border-radius: 24px; {{ $compact ? 'width: 158px; height: 158px;' : 'aspect-ratio: 1.15;' }}">
            @if($cover)
                <img src="{{ $cover }}" alt="{{ $p->localized_title }}" loading="lazy"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]">
            @endif
        </div>
        @if($compact)
            <div style="padding-top: 8px; display: flex; flex-direction: column; gap: 3px;">
                <span class="font-medium text-black truncate {{ $fa }}" style="font-size: 12px; line-height: 16px;">{{ $p->localized_title }}</span>
                <span class="text-[#6B7280] truncate {{ $fa }}" style="font-size: 12px; line-height: 16px;">
                    {{ $priceLabel }} / {{ $isRtl ? 'الليلة' : 'night' }} · ★ {{ $ratingAvg ?? ($isRtl ? 'جديد' : 'New') }}
                </span>
            </div>
        @else
            <div style="padding-top: 12px; display: flex; flex-direction: column; gap: 4px;">
                <div class="flex items-center justify-between" style="gap: 8px;">
                    <h3 class="font-bold text-black truncate {{ $fa }}" style="font-size: 15px; line-height: 20px;">{{ $p->localized_title }}</h3>
                    <span class="shrink-0 tabular-nums text-[#1A1A1A]" style="font-size: 13px; line-height: 18px;">
                        <span style="font-size: 13px;">★</span>
                        @if($ratingAvg)
                            <span class="font-bold">{{ $ratingAvg }}</span>
                            <span class="text-[#6B7280]">({{ $ratingCount }})</span>
                        @else
                            <span class="font-bold {{ $fa }}">{{ $isRtl ? 'جديد' : 'New' }}</span>
                        @endif
                    </span>
                </div>
                @if($meta !== '')
                    <p class="text-[#6B7280] truncate {{ $fa }}" style="font-size: 13px; line-height: 18px;">{{ $meta }}</p>
                @endif
                <p class="font-bold text-black tabular-nums {{ $fa }}" style="font-size: 14px; line-height: 20px; margin-top: 2px;">
                    {{ $priceLabel }} <span class="font-normal text-[#6B7280]" style="font-size: 13px;">/ {{ $isRtl ? 'الليلة' : 'night' }}</span>
                </p>
            </div>
        @endif
    </a>

    {{-- Guest-favorite badge --}}
    @if($guestFavorite)
        <span class="absolute bg-white font-bold text-[#1A1A1A] {{ $fa }}"
              style="top: 12px; inset-inline-start: 12px; padding: 4px 10px; border-radius: 999px; font-size: 11px; line-height: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.12); pointer-events: none;">
            {{ $isRtl ? 'مفضل الضيوف' : 'Guest favorite' }}
        </span>
    @endif

    {{-- Heart — sibling overlay, NOT inside the link --}}
    <div class="absolute" style="top: 12px; inset-inline-end: 12px;">
        @if($me)
            <button type="button" x-data="{ liked: @js($liked) }"
                    @click.stop.prevent="liked = !liked; fetch('/api/places/{{ $p->id }}/like', { method: liked ? 'POST' : 'DELETE', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })"
                    aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}"
                    class="calm-press-like flex items-center justify-center" style="width: {{ $heartSize }}px; height: {{ $heartSize }}px;">
                <svg width="{{ $heartSize - 6 }}" height="{{ $heartSize - 6 }}" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"
                     :fill="liked ? '#F88379' : 'rgba(0,0,0,0.45)'" fill="rgba(0,0,0,0.45)">
                    <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
                </svg>
            </button>
        @else
            <a href="{{ route('login', ['next' => request()->getRequestUri()]) }}"
               aria-label="{{ $isRtl ? 'سجّل الدخول للحفظ في المفضلة' : 'Sign in to save' }}"
               class="calm-press-like flex items-center justify-center" style="width: {{ $heartSize }}px; height: {{ $heartSize }}px;">
                <svg width="{{ $heartSize - 6 }}" height="{{ $heartSize - 6 }}" viewBox="0 0 24 24" fill="rgba(0,0,0,0.45)" stroke="#fff" stroke-width="1.8">
                    <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
                </svg>
            </a>
        @endif
    </div>
</div>
