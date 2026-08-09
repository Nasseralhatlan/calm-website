{{-- Server-rendered place card — mobile-app parity.
     $compact = true  → home carousels: square image radius 24, then
                        title (bold) / «العمارية · الرياض» (muted) / «SR 980».
     $compact = false → grids: same stack at grid width.
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
    // Card carousel = the host-curated "shown outside" set (featured_order),
    // capped like the API's carousel_photos. Falls back to the cover alone.
    $carousel = $compact ? collect() : $p->visiblePhotos()
        ->filter(fn ($ph) => $ph->featured_order !== null)
        ->sortBy('featured_order')
        ->take(10)
        ->map(fn ($ph) => $ph->url)
        ->values();
    if (! $compact && $carousel->isEmpty() && $cover) {
        $carousel = collect([$cover]);
    }
    $cardCity = $p->cityArea?->city;
    $areaName = $isRtl ? $p->cityArea?->name_ar : $p->cityArea?->name_en;
    $cityName = $cardCity ? ($isRtl ? $cardCity->name_ar : $cardCity->name_en) : null;
    $subtitle = collect([$areaName, $cityName])->filter()->implode(' · ');
    $liked = (bool) ($p->liked_by_me ?? false);
    $heartSize = 30;
    $priceLabel = 'SR '.number_format((int) $p->price);
@endphp
<div class="calm-press-card group relative shrink-0" @if($compact) style="width: clamp(126px, 37vw, 172px);" @endif>
    <a href="{{ route('places.show', $p) }}" class="block">
        <div class="relative overflow-hidden"
             style="background-color: #F3F4F6; border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle; aspect-ratio: 1; {{ $compact ? 'width: 100%;' : '' }}"
             @if(! $compact && $carousel->count() > 1) x-data="{ ci: 0 }" @endif>
            @if($compact)
                @if($cover)
                    <img src="{{ $cover }}" alt="{{ $p->localized_title }}" loading="lazy"
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]">
                @endif
            @elseif($carousel->count() > 1)
                {{-- Featured-photos carousel (scroll-snap + app-spec dots) --}}
                <div class="flex overflow-x-auto calm-hide-scroll w-full h-full" style="scroll-snap-type: x mandatory; cursor: grab;"
                     x-init="window.calmDragScroll && calmDragScroll($el)"
                     @scroll="ci = Math.min({{ $carousel->count() - 1 }}, Math.round(Math.abs($el.scrollLeft) / $el.clientWidth))">
                    @foreach($carousel as $u)
                        <img src="{{ $u }}" alt="{{ $p->localized_title }}" loading="lazy"
                             class="w-full h-full object-cover shrink-0"
                             style="scroll-snap-align: center; scroll-snap-stop: always;">
                    @endforeach
                </div>
                <div class="absolute flex items-center justify-center" style="bottom: 10px; left: 0; right: 0; gap: 4px; pointer-events: none;">
                    @foreach($carousel as $di => $u)
                        {{-- Full style lives in the binding — Alpine string :style replaces the static attr --}}
                        <span class="calm-round"
                              :style="'height: 5px; border-radius: 3px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.25); transition: all 0.25s; ' + ({{ $di }} === ci ? 'width: 14px; opacity: 1;' : 'width: 5px; opacity: 0.55;')"></span>
                    @endforeach
                </div>
            @elseif($carousel->isNotEmpty())
                <img src="{{ $carousel->first() }}" alt="{{ $p->localized_title }}" loading="lazy"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]">
            @endif
        </div>
        <div style="padding-top: 10px; display: flex; flex-direction: column; gap: 3px;">
            <span class="font-bold text-black truncate {{ $fa }}" style="font-size: {{ $compact ? '13px' : '15px' }}; line-height: 1.35;">{{ $p->localized_title }}</span>
            @if($subtitle !== '')
                <span class="truncate {{ $fa }}" style="font-size: {{ $compact ? '12px' : '13px' }}; line-height: 1.3; color: #AAAAAA;">{{ $subtitle }}</span>
            @endif
            <span class="font-bold text-black tabular-nums" style="font-size: {{ $compact ? '13px' : '14px' }}; line-height: 1.3;"><bdi dir="ltr">{{ $priceLabel }}</bdi></span>
        </div>
    </a>

    {{-- Heart — sibling overlay at inline-start (visual right in RTL, like the app) --}}
    <div class="absolute" style="top: 12px; inset-inline-start: 12px;">
        @if($me)
            <button type="button" x-data="{ liked: @js($liked) }"
                    @click.stop.prevent="liked = !liked; fetch('/api/places/{{ $p->id }}/like', { method: liked ? 'POST' : 'DELETE', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })"
                    aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}"
                    class="calm-press-like flex items-center justify-center" style="width: {{ $heartSize }}px; height: {{ $heartSize }}px;">
                <svg width="{{ $heartSize - 6 }}" height="{{ $heartSize - 6 }}" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"
                     :fill="liked ? '#F88379' : 'rgba(0,0,0,0.2)'" fill="rgba(0,0,0,0.2)"
                     style="filter: drop-shadow(0 1px 4px rgba(0,0,0,0.25));">
                    <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
                </svg>
            </button>
        @else
            <button type="button" x-data @click.stop.prevent="$dispatch('calm-open-login')"
                    aria-label="{{ $isRtl ? 'سجّل الدخول للحفظ في المفضلة' : 'Sign in to save' }}"
                    class="calm-press-like flex items-center justify-center" style="width: {{ $heartSize }}px; height: {{ $heartSize }}px;">
                <svg width="{{ $heartSize - 6 }}" height="{{ $heartSize - 6 }}" viewBox="0 0 24 24" fill="rgba(0,0,0,0.2)" stroke="#fff" stroke-width="2"
                     style="filter: drop-shadow(0 1px 4px rgba(0,0,0,0.25));">
                    <path d="M12 20.5s-7.5-4.8-9.5-9.2C1 7.6 3.2 4.5 6.4 4.5c2 0 3.6 1.1 5.6 3.3 2-2.2 3.6-3.3 5.6-3.3 3.2 0 5.4 3.1 3.9 6.8-2 4.4-9.5 9.2-9.5 9.2z"></path>
                </svg>
            </button>
        @endif
    </div>
</div>
