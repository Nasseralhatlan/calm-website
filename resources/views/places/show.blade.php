@extends('layouts.app')

@php
    use App\Enums\AttributeType;
    use Illuminate\Support\Str;

    $locale  = app()->getLocale();
    $isRtl   = $locale === 'ar';
    $placeLabel  = $isRtl ? ($place->type?->name_ar ?? '') : ($place->type?->name_en ?? '');
    $ogTitle = ($place->title ?: $placeLabel) . ' · Calm';
    $ogDescription = $place->description
        ? Str::limit(strip_tags($place->description), 180)
        : ($isRtl ? 'إقامة فاخرة من كالم' : 'A luxury stay on Calm');
    // OG image = the host's cover (first "shown outside" photo), else first photo.
    $firstPhoto = $place->coverPhoto ?? $place->photos->first();
    $ogImage = $firstPhoto?->url;
    $ogUrl   = url()->current();
@endphp

@section('title', 'Calm — ' . ($place->title ?: $place->id))

@section('meta')
    <meta name="description" content="{{ $ogDescription }}">
    <meta property="og:site_name" content="Calm">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:secure_url" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $ogTitle }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    @if($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
@endsection

@section('body')
@php
    $fa     = $isRtl ? 'font-arabic' : '';
    $start  = $isRtl ? 'text-right' : 'text-left';

    // ─── Schema adapters ──────────────────────────────────────────────────
    // The new schema unifies master's HostFacility + HostAmenity behind one
    // `place_attributes` row per chosen attribute. We split them back into
    // "facilities" (numeric-type, have a count + can have linked photos)
    // and "amenities" (boolean-type, no count) so the page still has the
    // same visual rhythm as master.
    $allImages   = $place->photos->values();
    $extraImages = $place->photos->whereNull('place_attribute_id')->values();

    $facilities = $place->attributeValues
        ->filter(fn ($pa) => $pa->attribute && $pa->attribute->type === AttributeType::Number)
        ->values();

    $amenities = $place->attributeValues
        ->filter(fn ($pa) => $pa->attribute && $pa->attribute->type !== AttributeType::Number)
        ->values();

    // For each facility, fetch the linked photos via place_attribute_id.
    // (`place_photos.place_attribute_id` references the catalog `attributes.id`,
    // so we filter by the attribute's id — not by the PlaceAttribute row id.)
    $facilityImages = fn ($pa) => $place->photos
        ->where('place_attribute_id', $pa->attribute_id)
        ->values();

    // Map: photo's place_attribute_id → DOM anchor the photo tour scrolls to.
    $sectionKeyFor = fn ($img) => $img->place_attribute_id
        ? ('attr-' . $img->place_attribute_id)
        : 'extras';

    // Group ALL amenities by their AttributeGroup (master's amenityGroups).
    // Powers the bottom "Show all amenities" sheet AND satisfies the
    // "grouping of attributes" ask from this turn.
    $groupedAmenities = [];
    foreach ($amenities->groupBy(fn ($pa) => $pa->attribute->group?->id) as $gid => $items) {
        $group = $items->first()->attribute->group;
        if ($group && $items->count() > 0) {
            $groupedAmenities[] = [
                'label' => $isRtl ? $group->name_ar : $group->name_en,
                'items' => $items->all(),
            ];
        }
    }

    // Preview: first 10 amenities (Airbnb shows 10).
    $previewAmenities = $amenities->take(10);

    $description = trim((string) $place->description);

    // Per-day pricing — only the panel renders when any day differs from base.
    $dayPrices = [
        'sunday' => $place->price_sunday, 'monday' => $place->price_monday, 'tuesday' => $place->price_tuesday,
        'wednesday' => $place->price_wednesday, 'thursday' => $place->price_thursday,
        'friday' => $place->price_friday, 'saturday' => $place->price_saturday,
    ];
    $hasPerDay = collect($dayPrices)->filter(fn ($p) => $p > 0 && $p !== (int) $place->price)->isNotEmpty();
    $dayLabels = $isRtl
        ? ['sunday' => 'الأحد', 'monday' => 'الإثنين', 'tuesday' => 'الثلاثاء', 'wednesday' => 'الأربعاء', 'thursday' => 'الخميس', 'friday' => 'الجمعة', 'saturday' => 'السبت']
        : ['sunday' => 'Sun', 'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat'];

    $totalImages = $allImages->count();

    // ─── "Shown outside" showcase ─────────────────────────────────────────
    // The host curates up to 10 featured photos for the place page (ordered;
    // first = cover). The hero/carousel shows those; falls back to the full
    // set when the host hasn't featured any.
    $featuredImages = $place->photos
        ->whereNotNull('featured_order')
        ->sortBy('featured_order')
        ->values();
    $heroImages = $featuredImages->isNotEmpty() ? $featuredImages : $allImages;

    // ─── Grouped "view images" gallery ────────────────────────────────────
    // Section order follows the host's arrangement: facilities are ordered by
    // their earliest photo's sort_order, so a section the host pushed down
    // (e.g. the bathroom) leads later. Photoless facilities sink to the end.
    $galleryFacilities = $facilities
        ->sortBy(fn ($f) => $facilityImages($f)->isNotEmpty()
            ? $facilityImages($f)->min('sort_order')
            : PHP_INT_MAX)
        ->values();
@endphp

<div
    class="min-h-screen bg-white text-[#222]"
    style="overflow-x: clip;"
    dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
    x-data="{
        gallery: false,
        sheet: null,
        description: false,
        openGallery(sectionKey) {
            this.gallery = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                const scroller = document.getElementById('gallery-scroll');
                if (scroller) scroller.scrollTo({ top: 0, behavior: 'instant' });
                if (sectionKey) setTimeout(() => this.scrollToGallerySection(sectionKey), 50);
            });
        },
        scrollToGallerySection(sectionKey) {
            const scroller = document.getElementById('gallery-scroll');
            const el = document.getElementById('gallery-section-' + sectionKey);
            if (el && scroller) scroller.scrollTo({ top: el.offsetTop - 180, behavior: 'smooth' });
        },
        closeGallery() { this.gallery = false; if (!this.sheet && !this.description) document.body.style.overflow = ''; },
        openSheet(name) { this.sheet = name; document.body.style.overflow = 'hidden'; },
        closeSheet() { this.sheet = null; if (!this.gallery && !this.description) document.body.style.overflow = ''; },
        openDescription() {
            this.description = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                const scroller = document.getElementById('description-scroll');
                if (scroller) scroller.scrollTo({ top: 0, behavior: 'instant' });
            });
        },
        closeDescription() { this.description = false; if (!this.gallery && !this.sheet) document.body.style.overflow = ''; },
    }"
    @keydown.escape.window="closeGallery(); closeSheet(); closeDescription();"
>
    {{-- OWNER/ADMIN STATUS BANNER — pinned above the page so they always know
         the listing's review + active status while previewing it as a guest. --}}
    @if($showStatusBanner ?? false)
        @php
            $rpReview = match ($place->review_status) {
                \App\Enums\PlaceReviewStatus::Draft         => ['#9ca3af', '#e5e7eb'],
                \App\Enums\PlaceReviewStatus::PendingReview => ['#f59e0b', '#fde68a'],
                \App\Enums\PlaceReviewStatus::Approved      => ['#10b981', '#a7f3d0'],
                \App\Enums\PlaceReviewStatus::Rejected      => ['#ef4444', '#fecaca'],
            };
            $rpStatus = $place->status === \App\Enums\PlaceStatus::Active
                ? ['#10b981', '#a7f3d0'] : ['#9ca3af', '#e5e7eb'];
            $reviewText = $isRtl ? match ($place->review_status) {
                \App\Enums\PlaceReviewStatus::Draft         => 'مسودة',
                \App\Enums\PlaceReviewStatus::PendingReview => 'قيد المراجعة',
                \App\Enums\PlaceReviewStatus::Approved      => 'موافق عليه',
                \App\Enums\PlaceReviewStatus::Rejected      => 'مرفوض',
            } : str_replace('_', ' ', $place->review_status->value);
            $statusText = $isRtl
                ? ($place->status === \App\Enums\PlaceStatus::Active ? 'مفعّل' : 'موقوف')
                : $place->status->value;
            $bannerEdit = ($viewerIsAdmin ?? false) ? route('admin.places.edit', $place) : route('host.places.edit', $place);
            $bannerBack = ($viewerIsAdmin ?? false) ? route('admin.places.index') : route('user.places');
        @endphp
        <div class="w-full text-white" style="background-color: #222;" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
            <div class="max-w-7xl mx-auto w-full px-6 sm:px-10 lg:px-20 flex items-center justify-between flex-wrap"
                 style="padding-top: 10px; padding-bottom: 10px; gap: 10px 16px;">
                <div class="flex items-center flex-wrap" style="gap: 8px 12px;">
                    <span class="text-[12px] font-semibold opacity-80 {{ $fa }}">{{ $isRtl ? '👁️ معاينة — هذه نظرة الزائر' : '👁️ Preview — this is the guest view' }}</span>
                    <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider {{ $fa }}"
                          style="padding: 4px 11px 4px 8px; border-radius: 999px; gap: 6px; background-color: {{ $rpReview[0] }};">
                        <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $rpReview[1] }};"></span>
                        {{ ($isRtl ? 'المراجعة: ' : 'Review: ') . $reviewText }}
                    </span>
                    <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider {{ $fa }}"
                          style="padding: 4px 11px 4px 8px; border-radius: 999px; gap: 6px; background-color: {{ $rpStatus[0] }};">
                        <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $rpStatus[1] }};"></span>
                        {{ ($isRtl ? 'الحالة: ' : 'Status: ') . $statusText }}
                    </span>
                </div>
                <div class="flex items-center" style="gap: 8px;">
                    <a href="{{ $bannerEdit }}" class="inline-flex items-center text-[12px] font-bold bg-white text-[#222] {{ $fa }}"
                       style="padding: 6px 14px; border-radius: 999px; gap: 4px;">{{ $isRtl ? '✎ تعديل' : '✎ Edit' }}</a>
                    <a href="{{ $bannerBack }}" class="inline-flex items-center text-[12px] font-semibold text-white hover:opacity-80 {{ $fa }}"
                       style="padding: 6px 10px;">{{ $isRtl ? 'رجوع' : 'Back' }}</a>
                </div>
            </div>
        </div>
    @endif

    {{-- HEADER --}}
    <header class="w-full border-b border-[#ebebeb] sticky top-0 z-30"
            style="background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="px-6 sm:px-10 lg:px-20 h-16 sm:h-20 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
            </a>
            <form method="POST" action="{{ url('/locale/' . ($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                @csrf
                <button type="submit"
                        class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] px-4 py-3 transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                    {{ $locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-7xl mx-auto w-full px-6 sm:px-10 lg:px-20 py-8 sm:py-10">

        {{-- TITLE BLOCK — centered --}}
        <div class="text-center mb-6">
            <h1 class="text-[26px] sm:text-[32px] font-bold tracking-tight text-[#222] {{ $fa }}" style="line-height: 1.2;">
                {{ $place->title ?: $placeLabel }}
            </h1>
            <div class="flex flex-wrap items-center justify-center {{ $fa }}" style="gap: 8px; margin-top: 12px;">
                <span class="inline-flex items-center text-[13px] text-[#717171] bg-[#fafafa]" style="gap: 6px; padding: 4px 12px; border-radius: 999px; corner-shape: squircle;">
                    <span>{{ $place->type?->icon ?: '🏠' }}</span>
                    <span>{{ $placeLabel }}</span>
                </span>
                @php $city = $place->cityArea?->city; @endphp
                @if($city)
                    <span class="inline-flex items-center text-[13px] text-[#717171] bg-[#fafafa]" style="gap: 6px; padding: 4px 12px; border-radius: 999px; corner-shape: squircle;">
                        <span>{{ $city->avatar ?: '📍' }}</span>
                        <span>{{ $isRtl ? ($place->cityArea?->name_ar.' · '.$city->name_ar) : ($place->cityArea?->name_en.' · '.$city->name_en) }}</span>
                    </span>
                @endif
                @if($facilities->count() > 0)
                    <span class="text-[13px] text-[#717171] bg-[#fafafa]" style="padding: 4px 12px; border-radius: 999px; corner-shape: squircle;">
                        {{ $facilities->count() }} {{ $isRtl ? 'مرافق' : 'facilities' }}
                    </span>
                @endif
            </div>
        </div>

        {{-- AIRBNB-STYLE MOSAIC (desktop) / SCROLL CAROUSEL (mobile) --}}
        @if($heroImages->count() > 0)
            @php $imgCount = $heroImages->count(); @endphp
            <div class="hidden sm:block relative">
                @if($imgCount === 1)
                    <div class="r-ios-xl overflow-hidden bg-[#f7f7f7]" style="height: 480px;">
                        <button type="button" @click="openGallery('{{ $sectionKeyFor($heroImages[0]) }}')" class="block w-full h-full overflow-hidden group">
                            <img src="{{ $heroImages[0]->url }}" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700" alt="" loading="eager">
                        </button>
                    </div>
                @elseif($imgCount === 2)
                    <div class="grid grid-cols-2 gap-2 r-ios-xl overflow-hidden" style="height: 480px;">
                        @foreach($heroImages as $i => $img)
                            <button type="button" @click="openGallery('{{ $sectionKeyFor($img) }}')" class="group relative overflow-hidden bg-[#f7f7f7]">
                                <img src="{{ $img->url }}" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                            </button>
                        @endforeach
                    </div>
                @elseif($imgCount === 3)
                    <div class="r-ios-xl overflow-hidden" style="display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(2, 1fr); gap: 8px; height: 480px;">
                        <button type="button" @click="openGallery('{{ $sectionKeyFor($heroImages[0]) }}')" style="grid-column: span 2; grid-row: span 2;" class="group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $heroImages[0]->url }}" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700" alt="" loading="eager">
                        </button>
                        <button type="button" @click="openGallery('{{ $sectionKeyFor($heroImages[1]) }}')" class="group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $heroImages[1]->url }}" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700" alt="" loading="lazy">
                        </button>
                        <button type="button" @click="openGallery('{{ $sectionKeyFor($heroImages[2]) }}')" class="group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $heroImages[2]->url }}" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700" alt="" loading="lazy">
                        </button>
                    </div>
                @else
                    @php $more = $totalImages - 4; @endphp
                    <div class="r-ios-xl overflow-hidden" style="display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr); gap: 8px; height: 480px;">
                        <button type="button" @click="openGallery('{{ $sectionKeyFor($heroImages[0]) }}')" style="grid-column: span 2; grid-row: span 2;" class="group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $heroImages[0]->url }}" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700" alt="" loading="eager">
                        </button>
                        @for($i = 1; $i <= 3; $i++)
                            <button type="button" @click="openGallery('{{ $sectionKeyFor($heroImages[$i]) }}')" class="group relative overflow-hidden bg-[#f7f7f7]">
                                <img src="{{ $heroImages[$i]->url }}" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700" alt="" loading="lazy">
                            </button>
                        @endfor
                        <button type="button" @click="openGallery('')" class="group relative overflow-hidden flex flex-col items-center justify-center text-white bg-[#222] hover:bg-black transition-colors {{ $fa }}">
                            @if($more > 0)
                                <div class="text-[36px] font-bold leading-none tabular-nums">+{{ $more }}</div>
                                <div class="mt-2 text-[13px] font-semibold">{{ $isRtl ? 'صورة أخرى' : 'more photos' }}</div>
                            @else
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                                <div class="mt-2 text-[13px] font-semibold">{{ $isRtl ? 'عرض الكل' : 'View all' }}</div>
                            @endif
                        </button>
                    </div>
                @endif
            </div>

            {{-- Mobile horizontal scroll carousel --}}
            <div class="sm:hidden"
                 x-data="{
                    idx: 0, total: {{ $imgCount }},
                    onScroll(el) { const w = el.clientWidth; if (w) this.idx = Math.round(Math.abs(el.scrollLeft) / w); },
                    prev() { const t = this.$refs.heroTrack; if (t) t.scrollBy({ left: -t.clientWidth, behavior: 'smooth' }); },
                    next() { const t = this.$refs.heroTrack; if (t) t.scrollBy({ left:  t.clientWidth, behavior: 'smooth' }); },
                 }">
                <div class="relative overflow-hidden bg-[#f7f7f7]" style="border-radius: 20px; corner-shape: squircle;" dir="ltr">
                    {{-- Hero carousel is always LTR — photos read in the same
                         visual order regardless of the page's reading direction. --}}
                    <div class="flex no-scrollbar" style="overflow-x: auto; overflow-y: hidden; scroll-snap-type: x mandatory; aspect-ratio: 4 / 3; width: 100%;"
                         x-ref="heroTrack" @scroll.passive="onScroll($event.target)" dir="ltr">
                        @foreach($heroImages as $i => $img)
                            <button type="button" @click="openGallery('{{ $sectionKeyFor($img) }}')" style="width: 100%; height: 100%; flex-shrink: 0; scroll-snap-align: center;">
                                <img src="{{ $img->url }}" style="width: 100%; height: 100%; object-fit: cover; pointer-events: none;" alt="" draggable="false" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                            </button>
                        @endforeach
                    </div>
                    @if($imgCount > 1)
                        <div class="absolute bottom-3 {{ $isRtl ? 'left-3' : 'right-3' }} text-white text-xs font-bold pointer-events-none"
                             style="background: rgba(0,0,0,0.6); padding: 5px 11px; border-radius: 999px; corner-shape: squircle; backdrop-filter: blur(8px);" dir="ltr">
                            <span x-text="idx + 1"></span> <span class="opacity-60">/</span> {{ $imgCount }}
                        </div>
                        <button type="button" @click.stop="prev()" aria-label="previous"
                                class="absolute top-1/2 -translate-y-1/2 left-3 flex items-center justify-center text-[#222] bg-white hover:bg-[#f7f7f7] active:scale-95 transition-all"
                                style="width: 36px; height: 36px; border-radius: 999px; corner-shape: squircle; box-shadow: 0 4px 12px rgba(0,0,0,0.18);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                        <button type="button" @click.stop="next()" aria-label="next"
                                class="absolute top-1/2 -translate-y-1/2 right-3 flex items-center justify-center text-[#222] bg-white hover:bg-[#f7f7f7] active:scale-95 transition-all"
                                style="width: 36px; height: 36px; border-radius: 999px; corner-shape: squircle; box-shadow: 0 4px 12px rgba(0,0,0,0.18);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    @endif
                </div>
                @if($imgCount > 1)
                    @if($imgCount <= 12)
                        <div class="flex justify-center items-center" style="gap: 6px; margin-top: 14px;">
                            @foreach($heroImages as $i => $img)
                                <span class="block transition-all"
                                      :style="idx === {{ $i }} ? 'width: 20px; height: 6px; border-radius: 999px; background-color: #222;' : 'width: 6px; height: 6px; border-radius: 999px; background-color: #9ca3af;'"></span>
                            @endforeach
                        </div>
                    @endif
                    <div class="flex justify-center" style="margin-top: 12px;">
                        <button type="button" @click="openGallery('')"
                                class="inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7] active:scale-[0.98] transition-all {{ $fa }}"
                                style="gap: 6px; padding: 7px 14px; font-size: 13px; border: 1px solid #222; border-radius: 999px; corner-shape: squircle;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            <span>{{ $isRtl ? "عرض كل الصور ({$totalImages})" : "View all {$totalImages} photos" }}</span>
                        </button>
                    </div>
                @endif
            </div>
        @endif

        {{-- ── TITLE + PRICE summary row directly under the hero ── --}}
        <div class="flex items-end justify-between flex-wrap {{ $fa }}"
             style="gap: 16px; margin-top: 32px; padding-bottom: 28px; border-bottom: 1px solid #ebebeb;">
            <div class="{{ $start }}">
                <h2 class="text-[20px] sm:text-[24px] font-bold text-[#222]" style="line-height: 1.25;">
                    {{ $place->title ?: $placeLabel }}
                </h2>
                <p class="text-[14px] text-[#717171]" style="margin-top: 4px;">
                    @if($city)
                        {{ $isRtl ? ($place->cityArea?->name_ar.' · '.$city->name_ar) : ($place->cityArea?->name_en.' · '.$city->name_en) }}
                    @else
                        {{ $placeLabel }}
                    @endif
                </p>
            </div>
            <div class="text-end {{ $fa }}">
                <div class="text-[24px] sm:text-[28px] font-bold text-[#222] tabular-nums" dir="ltr" style="line-height: 1.1;">
                    {{ number_format($place->price) }} <span class="text-[14px] text-[#717171]">SAR</span>
                </div>
                <div class="text-[12px] text-[#717171]" style="margin-top: 2px;">{{ $isRtl ? 'السعر الأساسي / ليلة' : 'Base price / night' }}</div>
            </div>
        </div>

        {{-- FULL-WIDTH BODY --}}
        <div style="margin-top: 48px;">

            {{-- DESCRIPTION (line-clamp 6 so more of the text shows by default;
                 Show-more button is smaller + only fires for genuinely long copy) --}}
            @if($description)
                <section class="{{ $start }} border-b border-[#ebebeb]" style="padding-bottom: 56px;">
                    <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 20px;">
                        {{ $isRtl ? 'الوصف' : 'About this place' }}
                    </h2>
                    <p class="text-[16px] text-[#222] {{ $fa }}"
                       style="line-height: 1.7; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 6; line-clamp: 6; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">{{ $description }}</p>
                    @if(mb_strlen($description) > 400)
                        <button type="button" @click="openDescription()"
                                class="inline-flex items-center font-semibold text-[#222] hover:bg-[#ebebeb] transition-colors {{ $fa }}"
                                style="margin-top: 14px; padding: 8px 16px; font-size: 13px; background-color: #f7f7f7; border-radius: 12px; corner-shape: squircle;">
                            <span>{{ $isRtl ? 'عرض المزيد' : 'Show more' }}</span>
                        </button>
                    @endif
                </section>
            @endif

            {{-- ── PER-DAY PRICING — only renders when at least one day differs
                 from the base price; the title-row above already shows base. ── --}}
            @if($hasPerDay)
                <section class="border-b border-[#ebebeb]" style="padding-top: 56px; padding-bottom: 56px;">
                    <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}" style="margin-bottom: 20px;">
                        {{ $isRtl ? 'السعر لكل يوم' : 'Per-day pricing' }}
                    </h2>
                    <div class="grid grid-cols-7" style="gap: 8px;">
                        @foreach($dayLabels as $day => $label)
                            @php
                                $p = $dayPrices[$day] > 0 ? $dayPrices[$day] : (int) $place->price;
                                $isCustom = $dayPrices[$day] > 0 && $dayPrices[$day] !== (int) $place->price;
                            @endphp
                            <div class="text-center" style="padding: 12px 6px; border-radius: 14px; corner-shape: squircle; background-color: {{ $isCustom ? '#fff1ef' : '#fafafa' }};">
                                <div class="text-[11px] font-bold uppercase {{ $isCustom ? 'text-[#F88379]' : 'text-[#717171]' }} {{ $fa }}">{{ $label }}</div>
                                <div class="text-[15px] font-bold text-[#222] tabular-nums" dir="ltr" style="margin-top: 4px;">{{ number_format($p) }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- قائمة المرافق — grouped by AttributeGroup, with attribute emojis,
                 limited preview, and a Show-all sheet (same pattern as amenities). --}}
            @php
                // Group facilities by their AttributeGroup so reviewers see the
                // facilities sorted by living-area / outdoor / activities / etc.
                $facilitiesByGroup = $facilities->groupBy(fn ($pa) => $pa->attribute->group?->id);
                // Inline preview cap — anything beyond fits behind the Show-all sheet.
                $facilityPreviewLimit = 6;
                $facilitiesPreview = $facilities->take($facilityPreviewLimit);
                $facilitiesPreviewByGroup = $facilitiesPreview->groupBy(fn ($pa) => $pa->attribute->group?->id);
            @endphp
            @if($facilities->count() > 0)
                <section class="border-b border-[#ebebeb]" style="padding-top: 56px; padding-bottom: 56px;">
                    <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}" style="margin-bottom: 6px;">
                        {{ $isRtl ? 'قائمة المرافق' : 'Facilities list' }}
                    </h2>
                    <p class="text-[15px] text-[#717171] {{ $start }} {{ $fa }}" style="margin-bottom: 24px;">
                        {{ $facilities->count() }} {{ $isRtl ? 'مرفق مختار' : 'facilities selected' }}
                    </p>

                    @foreach($facilitiesPreviewByGroup as $groupId => $items)
                        @php $group = $items->first()->attribute->group; @endphp
                        @if($group)
                            <h3 class="text-[13px] font-bold text-[#717171] uppercase tracking-wider {{ $fa }}"
                                style="margin-top: {{ $loop->first ? '0' : '24px' }}; margin-bottom: 12px;">
                                {{ $isRtl ? $group->name_ar : $group->name_en }}
                            </h3>
                        @endif
                        <ul class="{{ $start }} {{ $fa }}" style="list-style: none; padding: 0; margin: 0;">
                            @foreach($items as $f)
                                <li style="padding: 14px 0; border-bottom: 1px solid #ebebeb;" x-data="{ expanded: false }">
                                    <div class="flex items-center" style="gap: 14px;">
                                        <span class="shrink-0" style="font-size: 22px; line-height: 1; width: 32px; text-align: center;">{{ $f->attribute->icon ?: '·' }}</span>
                                        <span class="text-[15px] sm:text-[16px] text-[#222] flex-1 font-medium">{{ $isRtl ? $f->attribute->name_ar : $f->attribute->name_en }}</span>
                                        <span class="text-[14px] font-bold text-[#222] tabular-nums" dir="ltr">×{{ (int) ($f->value ?: 1) }}</span>
                                    </div>
                                    @if($f->description)
                                        <div style="padding-{{ $isRtl ? 'right' : 'left' }}: 46px; margin-top: 8px;">
                                            <p class="text-[14px] text-[#717171]"
                                               :style="expanded ? 'line-height: 1.65;' : 'line-height: 1.65; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;'">{{ $f->description }}</p>
                                            @if(mb_strlen($f->description) > 100)
                                                <button type="button" @click="expanded = !expanded"
                                                        class="text-[13px] font-semibold text-[#222] underline underline-offset-2 hover:text-black" style="margin-top: 6px;">
                                                    <span x-show="!expanded">{{ $isRtl ? 'عرض المزيد' : 'Show more' }}</span>
                                                    <span x-show="expanded" x-cloak>{{ $isRtl ? 'عرض أقل' : 'Show less' }}</span>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endforeach

                    @if($facilities->count() > $facilityPreviewLimit)
                        <button type="button" @click="openSheet('facilities')"
                                class="inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                                style="margin-top: 24px; padding: 12px 22px; border: 1px solid #222; border-radius: 12px; corner-shape: squircle;">
                            {{ $isRtl ? "عرض كل المرافق ({$facilities->count()})" : "Show all {$facilities->count()} facilities" }}
                        </button>
                    @endif
                </section>
            @endif

            {{-- FACILITIES IMAGES (carousel — transform-based, always LTR) --}}
            @if($facilities->count() > 0)
                <section class="border-b border-[#ebebeb]" dir="ltr"
                         style="padding-top: 56px; padding-bottom: 56px; direction: ltr; unicode-bidi: isolate;"
                         x-data="{
                            idx: 0, total: {{ $facilities->count() }}, slideWidth: 200,
                            startX: 0, deltaX: 0, dragging: false, dragged: false,
                            init() { this.measure(); window.addEventListener('resize', () => this.measure()); },
                            measure() { const t = this.$refs.track; const first = t && t.firstElementChild; if (first) this.slideWidth = first.offsetWidth + 20; },
                            onStart(e) { this.dragging = true; this.startX = (e.touches ? e.touches[0].clientX : e.clientX); this.deltaX = 0; },
                            onMove(e) { if (!this.dragging) return; const x = (e.touches ? e.touches[0].clientX : e.clientX); this.deltaX = x - this.startX; },
                            onEnd() {
                                if (!this.dragging) return;
                                this.dragging = false;
                                if (Math.abs(this.deltaX) > 5) { this.dragged = true; setTimeout(() => this.dragged = false, 80); }
                                if (this.deltaX < -50) this.next();
                                else if (this.deltaX > 50) this.prev();
                                this.deltaX = 0;
                            },
                            offset() { return -this.idx * this.slideWidth + this.deltaX; },
                            go(i) { this.idx = ((i % this.total) + this.total) % this.total; },
                            next() { this.go(this.idx + 1); }, prev() { this.go(this.idx - 1); },
                         }">
                    <div class="flex items-end justify-between gap-4">
                        <div style="text-align: start;" class="{{ $fa }}">
                            <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222]">
                                {{ $isRtl ? 'صور المرافق' : 'Facilities images' }}
                            </h2>
                            <p class="text-[15px] text-[#717171]" style="margin-top: 4px;">
                                {{ $isRtl ? 'استكشف كل مساحات المكان' : 'Browse every space of the place' }}
                            </p>
                        </div>
                        <div class="flex items-center shrink-0" style="gap: 10px;">
                            <button type="button" @click="prev()" aria-label="previous"
                                    class="flex items-center justify-center text-[#222] hover:text-black active:scale-95 transition-all"
                                    style="width: 40px; height: 40px; border-radius: 999px; corner-shape: squircle; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </button>
                            <button type="button" @click="next()" aria-label="next"
                                    class="flex items-center justify-center text-[#222] hover:text-black active:scale-95 transition-all"
                                    style="width: 40px; height: 40px; border-radius: 999px; corner-shape: squircle; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </button>
                        </div>
                    </div>

                    <div class="overflow-hidden" dir="ltr"
                         style="margin-top: 28px; touch-action: pan-y; direction: ltr;"
                         @touchstart.passive="onStart" @touchmove.passive="onMove" @touchend.passive="onEnd">
                        <div x-ref="track" dir="ltr" class="flex flex-row"
                             :style="'direction: ltr; width: max-content; gap: 20px; transform: translateX(' + offset() + 'px); transition: transform ' + (dragging ? '0ms' : '300ms') + ' ease; will-change: transform;'">
                            @foreach($facilities as $f)
                                @php
                                    $facImages = $facilityImages($f);
                                    $firstImg = $facImages->first();
                                    $sectionKey = 'attr-' . $f->attribute_id;
                                @endphp
                                <div class="shrink-0" style="width: 180px; text-align: start;" x-data="{ expanded: false }">
                                    <button type="button"
                                            @click="{{ $firstImg ? "openGallery('".$sectionKey."')" : '' }}"
                                            class="block group {{ $firstImg ? 'cursor-zoom-in' : 'cursor-default' }}"
                                            style="width: 180px;" @if(! $firstImg) disabled @endif>
                                        @if($firstImg)
                                            <div class="overflow-hidden bg-[#f7f7f7]" style="width: 180px; height: 180px; border-radius: 20px; corner-shape: squircle;">
                                                <img src="{{ $firstImg->url }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="" loading="lazy">
                                            </div>
                                        @else
                                            <div class="flex items-center justify-center text-4xl text-[#cecece] bg-[#f7f7f7]" style="width: 180px; height: 180px; border-radius: 20px; corner-shape: squircle;">
                                                {{ $f->attribute->icon ?: '🏠' }}
                                            </div>
                                        @endif
                                        <div class="flex items-center gap-2 {{ $fa }}" style="margin-top: 12px;">
                                            <h3 class="text-[15px] font-bold text-[#222]">
                                                {{ $isRtl ? $f->attribute->name_ar : $f->attribute->name_en }}
                                            </h3>
                                            <span class="text-xs font-bold text-[#222] bg-[#f7f7f7] tabular-nums"
                                                  style="padding: 3px 9px; border-radius: 999px; corner-shape: squircle;">
                                                {{ (int) ($f->value ?: 1) }}
                                            </span>
                                        </div>
                                    </button>
                                    @if($f->description)
                                        <div class="{{ $fa }}" style="margin-top: 4px;">
                                            <p class="text-[13px] text-[#717171]"
                                               :style="expanded ? 'line-height: 1.55;' : 'line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;'">{{ $f->description }}</p>
                                            @if(mb_strlen($f->description) > 90)
                                                <button type="button" @click.stop="expanded = !expanded"
                                                        class="text-[12px] font-semibold text-[#222] underline underline-offset-2" style="margin-top: 4px;">
                                                    <span x-show="!expanded">{{ $isRtl ? 'عرض المزيد' : 'Show more' }}</span>
                                                    <span x-show="expanded" x-cloak>{{ $isRtl ? 'عرض أقل' : 'Show less' }}</span>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($facilities->count() > 1 && $facilities->count() <= 20)
                        <div class="flex flex-row justify-center items-center" style="gap: 6px; margin-top: 20px; direction: ltr;" dir="ltr">
                            @foreach($facilities as $i => $f)
                                <button type="button" @click="go({{ $i }})" aria-label="go to slide {{ $i + 1 }}"
                                        class="block transition-all"
                                        :style="idx === {{ $i }} ? 'width: 24px; height: 6px; background: #222; border-radius: 999px;' : 'width: 6px; height: 6px; background: #cbd5e1; border-radius: 999px;'"></button>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            {{-- AMENITIES (preview + show-all sheet, grouped by AttributeGroup in the sheet) --}}
            @if($amenities->count() > 0)
                <section class="border-b border-[#ebebeb]" style="padding-top: 56px; padding-bottom: 56px;">
                    <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] mb-1 {{ $start }} {{ $fa }}">
                        {{ $isRtl ? 'ما يقدمه هذا المكان' : 'What this place offers' }}
                    </h2>
                    <p class="text-[15px] text-[#717171] mb-6 {{ $start }} {{ $fa }}">
                        {{ $amenities->count() }} {{ $isRtl ? 'ميزة متوفرة' : 'amenities available' }}
                    </p>

                    <div class="flex flex-wrap" style="gap: 12px; margin-top: 24px;">
                        @foreach($previewAmenities as $a)
                            <div class="inline-flex items-center bg-white hover:-translate-y-0.5 transition-all duration-200"
                                 style="gap: 10px; padding: 14px 18px; border-radius: 16px; corner-shape: squircle; box-shadow: 0 6px 18px rgba(0,0,0,0.06);">
                                <span class="leading-none shrink-0" style="font-size: 22px;">{{ $a->attribute->icon ?: '·' }}</span>
                                <span class="text-[15px] font-medium text-[#222] whitespace-nowrap {{ $fa }}">{{ $isRtl ? $a->attribute->name_ar : $a->attribute->name_en }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if($amenities->count() > 10)
                        <button type="button" @click="openSheet('amenities')"
                                class="mt-6 inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                                style="padding: 12px 22px; border: 1px solid #222; border-radius: 12px; corner-shape: squircle;">
                            {{ $isRtl ? "عرض جميع المميزات ({$amenities->count()})" : "Show all {$amenities->count()} amenities" }}
                        </button>
                    @endif
                </section>
            @endif

            {{-- ── CHECK-IN / CHECK-OUT — moved here so it lands right before
                 the rules, after the facilities sections. Arabic heading:
                 "وقت الدخول و الخروج". ── --}}
            <section class="border-b border-[#ebebeb]" style="padding-top: 56px; padding-bottom: 56px;">
                <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}" style="margin-bottom: 24px;">
                    {{ $isRtl ? 'وقت الدخول و الخروج' : 'Check-in & check-out' }}
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                    <div class="flex items-center bg-white {{ $start }}" style="gap: 16px; padding: 20px; border-radius: 20px; border: 1px solid #ebebeb; corner-shape: squircle;">
                        <span class="flex items-center justify-center bg-[#f7f7f7] shrink-0" style="width: 48px; height: 48px; border-radius: 16px; corner-shape: squircle; font-size: 22px;">🕒</span>
                        <div class="{{ $fa }}">
                            <div class="text-[12px] font-semibold text-[#717171] uppercase tracking-wider">{{ $isRtl ? 'الدخول' : 'Check-in' }}</div>
                            <div class="text-[20px] font-bold text-[#222] tabular-nums" dir="ltr" style="margin-top: 2px;">{{ $place->check_in_time }}</div>
                        </div>
                    </div>
                    <div class="flex items-center bg-white {{ $start }}" style="gap: 16px; padding: 20px; border-radius: 20px; border: 1px solid #ebebeb; corner-shape: squircle;">
                        <span class="flex items-center justify-center bg-[#f7f7f7] shrink-0" style="width: 48px; height: 48px; border-radius: 16px; corner-shape: squircle; font-size: 22px;">🕛</span>
                        <div class="{{ $fa }}">
                            <div class="text-[12px] font-semibold text-[#717171] uppercase tracking-wider">{{ $isRtl ? 'الخروج' : 'Check-out' }}</div>
                            <div class="text-[20px] font-bold text-[#222] tabular-nums" dir="ltr" style="margin-top: 2px;">{{ $place->check_out_time }}</div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- HOUSE RULES — text content the host wrote in the wizard --}}
            @if($place->rules)
                <section style="padding-top: 56px; padding-bottom: 56px;">
                    <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}" style="margin-bottom: 20px;">
                        {{ $isRtl ? 'قواعد المكان' : 'House rules' }}
                    </h2>
                    <p class="text-[15px] sm:text-[16px] text-[#222] leading-relaxed whitespace-pre-line {{ $start }} {{ $fa }}">{{ $place->rules }}</p>
                </section>
            @endif

        </div>

        {{-- FOOTER --}}
        <div class="border-t border-[#ebebeb] text-center" style="margin-top: 80px; padding-top: 56px;">
            <img src="/favicon.png" alt="Calm" class="h-10 w-auto mx-auto opacity-70">
            <div class="mt-3 text-[13px] text-[#717171] {{ $fa }}">
                © {{ date('Y') }} <span class="font-bold text-[#222]">Calm</span>.
                {{ $isRtl ? 'جميع الحقوق محفوظة.' : 'All rights reserved.' }}
            </div>
            <div class="mt-1 text-[12px] text-[#b0b0b0] {{ $fa }}">
                {{ $isRtl ? 'صُنع بحب في الرياض، المملكة العربية السعودية' : 'Made with ❤ in Riyadh, Saudi Arabia' }}
            </div>
        </div>
    </main>

    {{-- ─────────── DESCRIPTION MODAL ─────────── --}}
    <div x-show="description" x-cloak x-transition.opacity id="description-scroll"
         style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 60; background-color: #ffffff; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch;"
         dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
        <div class="sticky top-0 border-b border-[#ebebeb]"
             style="z-index: 2; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
            <div class="max-w-3xl mx-auto flex items-center justify-between px-6 sm:px-10 h-20">
                <button type="button" @click="closeDescription()"
                        class="w-10 h-10 flex items-center justify-center text-[#222] hover:bg-[#f7f7f7] transition-colors"
                        style="border-radius: 999px; corner-shape: squircle;" aria-label="close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <h2 class="text-[16px] sm:text-[18px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'الوصف' : 'About this place' }}</h2>
                <div class="w-10 h-10"></div>
            </div>
        </div>
        <div class="max-w-3xl mx-auto px-6 sm:px-10 {{ $start }}" style="padding-top: 32px; padding-bottom: 160px;">
            <p class="text-[16px] sm:text-[17px] text-[#222] {{ $fa }}" style="line-height: 1.8; white-space: pre-line;">{{ $description }}</p>
        </div>
    </div>

    {{-- ─────────── FACILITIES SHEET (grouped by AttributeGroup) ─────────── --}}
    <div x-show="sheet === 'facilities'" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.5);" @click="closeSheet()" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:max-w-2xl sm:max-h-[85vh] sm:h-fit bg-white flex flex-col"
             style="border-radius: 28px 28px 0 0; corner-shape: squircle;"
             x-show="sheet === 'facilities'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
            <div class="relative px-6 pt-6 pb-4 border-b border-[#ebebeb]">
                <button type="button" @click="closeSheet()"
                        class="absolute top-5 {{ $isRtl ? 'right-5' : 'left-5' }} w-9 h-9 flex items-center justify-center hover:bg-[#f7f7f7] rounded-full transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
                <div class="text-center font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'كل المرافق' : 'All facilities' }}</div>
            </div>
            <div class="overflow-y-auto p-6 sm:p-8 {{ $start }}">
                @foreach($facilitiesByGroup as $gid => $items)
                    @php $group = $items->first()->attribute->group; @endphp
                    <div class="{{ ! $loop->first ? 'mt-8 pt-8 border-t border-[#ebebeb]' : '' }}">
                        @if($group)
                            <h3 class="text-[14px] font-bold text-[#717171] uppercase tracking-wide mb-4 {{ $fa }}">
                                {{ $isRtl ? $group->name_ar : $group->name_en }}
                            </h3>
                        @endif
                        @foreach($items as $f)
                            <div class="flex items-center gap-4 py-3 border-b border-[#ebebeb] last:border-b-0">
                                <span class="text-[22px] leading-none w-8 text-center shrink-0">{{ $f->attribute->icon ?: '·' }}</span>
                                <span class="text-[16px] text-[#222] flex-1 {{ $fa }}">{{ $isRtl ? $f->attribute->name_ar : $f->attribute->name_en }}</span>
                                <span class="text-[14px] font-bold text-[#222] tabular-nums" dir="ltr">×{{ (int) ($f->value ?: 1) }}</span>
                            </div>
                            @if($f->description)
                                <p class="text-[13px] text-[#717171] {{ $fa }}" style="margin: 4px 0 8px 44px; line-height: 1.5;">{{ $f->description }}</p>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ─────────── AMENITIES SHEET (grouped by AttributeGroup) ─────────── --}}
    <div x-show="sheet === 'amenities'" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.5);" @click="closeSheet()" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:max-w-2xl sm:max-h-[85vh] sm:h-fit bg-white flex flex-col"
             style="border-radius: 28px 28px 0 0; corner-shape: squircle;"
             x-show="sheet === 'amenities'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
            <div class="relative px-6 pt-6 pb-4 border-b border-[#ebebeb]">
                <button type="button" @click="closeSheet()"
                        class="absolute top-5 {{ $isRtl ? 'right-5' : 'left-5' }} w-9 h-9 flex items-center justify-center hover:bg-[#f7f7f7] rounded-full transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
                <div class="text-center font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'كل ما يقدمه هذا المكان' : 'What this place offers' }}</div>
            </div>
            <div class="overflow-y-auto p-6 sm:p-8 {{ $start }}">
                @foreach($groupedAmenities as $i => $group)
                    <div class="{{ $i > 0 ? 'mt-8 pt-8 border-t border-[#ebebeb]' : '' }}">
                        <h3 class="text-[14px] font-bold text-[#717171] uppercase tracking-wide mb-4 {{ $fa }}">{{ $group['label'] }}</h3>
                        @foreach($group['items'] as $pa)
                            <div class="flex items-center gap-4 py-3 border-b border-[#ebebeb] last:border-b-0">
                                <span class="text-[22px] leading-none w-8 text-center shrink-0">{{ $pa->attribute->icon ?: '·' }}</span>
                                <span class="text-[16px] text-[#222] {{ $fa }}">{{ $isRtl ? $pa->attribute->name_ar : $pa->attribute->name_en }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ─────────── PHOTO TOUR GALLERY MODAL ─────────── --}}
    <div x-show="gallery" x-cloak x-transition.opacity id="gallery-scroll"
         style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 60; background-color: #ffffff; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch;"
         dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
        <div class="sticky top-0 border-b border-[#ebebeb]"
             style="z-index: 2; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
            <div class="max-w-7xl mx-auto flex items-center justify-between px-6 sm:px-10 lg:px-20 h-20">
                <button type="button" @click="closeGallery()"
                        class="w-10 h-10 flex items-center justify-center text-[#222] hover:bg-[#f7f7f7] transition-colors"
                        style="border-radius: 999px; corner-shape: squircle;" aria-label="close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <h2 class="text-[16px] sm:text-[18px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'جولة بالصور' : 'Photo tour' }}</h2>
                <div class="w-10 h-10"></div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 sm:px-10 lg:px-20" style="padding-bottom: 160px;">
            {{-- Horizontal thumbnails strip (jump-to anchors) --}}
            <div class="overflow-x-auto no-scrollbar pt-8 pb-4">
                <div class="flex gap-4 justify-start" style="width: max-content;">
                    @foreach($galleryFacilities as $f)
                        @php $facImages = $facilityImages($f); $sectionKey = 'attr-' . $f->attribute_id; @endphp
                        @if($facImages->count() > 0)
                            <button type="button" @click="scrollToGallerySection('{{ $sectionKey }}')"
                                    class="shrink-0 flex flex-col items-center" style="width: 104px;">
                                <img src="{{ $facImages->first()->url }}" class="block object-cover hover:opacity-90 transition-opacity"
                                     style="width: 92px; height: 92px; border-radius: 20px; corner-shape: squircle;" alt="" loading="lazy">
                                <div class="mt-2 text-[13px] font-semibold text-[#222] text-center {{ $fa }} truncate w-full">
                                    {{ $isRtl ? $f->attribute->name_ar : $f->attribute->name_en }}
                                </div>
                            </button>
                        @endif
                    @endforeach

                    @if($extraImages->count() > 0)
                        <button type="button" @click="scrollToGallerySection('extras')"
                                class="shrink-0 flex flex-col items-center" style="width: 104px;">
                            <img src="{{ $extraImages->first()->url }}" class="block object-cover hover:opacity-90 transition-opacity"
                                 style="width: 92px; height: 92px; border-radius: 20px; corner-shape: squircle;" alt="" loading="lazy">
                            <div class="mt-2 text-[13px] font-semibold text-[#222] text-center {{ $fa }} truncate w-full">
                                {{ $isRtl ? 'صور أخرى' : 'More' }}
                            </div>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Per-facility sections — ordered by the host's gallery arrangement --}}
            @foreach($galleryFacilities as $f)
                @php $facImages = $facilityImages($f); $sectionKey = 'attr-' . $f->attribute_id; @endphp
                @if($facImages->count() > 0)
                    <section id="gallery-section-{{ $sectionKey }}" style="padding-top: 32px; scroll-margin-top: 100px;">
                        <h3 class="text-[22px] sm:text-[26px] font-bold text-[#222] {{ $fa }}" style="line-height: 1.2;">
                            {{ $isRtl ? $f->attribute->name_ar : $f->attribute->name_en }}
                        </h3>
                        @if($f->description)
                            <p class="mt-2 text-[14px] sm:text-[15px] text-[#6B7280] {{ $fa }}" style="line-height: 1.6;">{{ $f->description }}</p>
                        @endif
                        <div class="mt-6 space-y-5">
                            @foreach($facImages as $img)
                                <img src="{{ $img->url }}" class="w-full block bg-[#f7f7f7]"
                                     style="border-radius: 40px; corner-shape: squircle;" alt="" loading="lazy">
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if($extraImages->count() > 0)
                <section id="gallery-section-extras" style="padding-top: 32px; scroll-margin-top: 100px;">
                    <h3 class="text-[22px] sm:text-[26px] font-bold text-[#222] {{ $fa }}" style="line-height: 1.2;">
                        {{ $isRtl ? 'صور أخرى' : 'More photos' }}
                    </h3>
                    <p class="mt-2 text-[14px] text-[#6B7280] {{ $fa }}">
                        {{ $extraImages->count() }} {{ $isRtl ? 'صورة' : ($extraImages->count() === 1 ? 'photo' : 'photos') }}
                    </p>
                    <div class="mt-5 space-y-4">
                        @foreach($extraImages as $img)
                            <img src="{{ $img->url }}" class="w-full block bg-[#f7f7f7]"
                                 style="border-radius: 20px; corner-shape: squircle;" alt="" loading="lazy">
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>

</div>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    [x-cloak] { display: none !important; }
</style>
@endsection
