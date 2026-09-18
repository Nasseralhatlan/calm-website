@extends('layouts.app')

@php
    use App\Enums\AttributeType;
    use Illuminate\Support\Str;

    $locale  = app()->getLocale();
    $isRtl   = $locale === 'ar';
    $placeLabel  = $isRtl ? ($place->type?->name_ar ?? '') : ($place->type?->name_en ?? '');
    $ogTitle = ($place->localized_title ?: $placeLabel) . ' · Calm';
    $ogDescription = $place->localized_description
        ? Str::limit(strip_tags($place->localized_description), 180)
        : ($isRtl ? 'إقامة فاخرة من كالم' : 'A luxury stay on Calm');
    // OG image = the host's cover (first "shown outside" photo), else first photo.
    $firstPhoto = $place->coverPhoto ?? $place->photos->first();
    $ogImage = $firstPhoto?->url;
    $ogUrl   = url()->current();
@endphp

@section('title', 'Calm — ' . ($place->localized_title ?: $place->id))

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

    // Ordered by the admin-controlled attribute sort (sort_order, then name) so
    // the place page matches the add-place wizard and the app API.
    $sortByAttribute = fn ($pa) => [$pa->attribute->sort_order ?? 0, $pa->attribute->name_en ?? ''];

    $facilities = $place->attributeValues
        ->filter(fn ($pa) => $pa->attribute && $pa->attribute->type === AttributeType::Number)
        ->sortBy($sortByAttribute)
        ->values();

    $amenities = $place->attributeValues
        ->filter(fn ($pa) => $pa->attribute && $pa->attribute->type !== AttributeType::Number)
        ->sortBy($sortByAttribute)
        ->values();

    // The most important amenities, shown in a dedicated "Highlights" block
    // above the preview. They still appear in the grouped list below.
    $highlightedAmenities = $amenities
        ->filter(fn ($pa) => $pa->attribute->is_highlighted)
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

    // ALL attributes (facilities + amenities) grouped for the «كل المرافق
    // و المميزات» modal — the app lists everything with a count badge.
    $groupedAll = [];
    foreach ($place->attributeValues->filter(fn ($pa) => $pa->attribute)->sortBy($sortByAttribute)->groupBy(fn ($pa) => $pa->attribute->group?->id) as $items) {
        $group = $items->first()->attribute->group;
        if ($group && $items->count() > 0) {
            $groupedAll[] = [
                'label' => $isRtl ? $group->name_ar : $group->name_en,
                'items' => $items->values()->all(),
            ];
        }
    }

    // Photo-tour grid rows: side-by-side pairs; an odd count promotes the 3rd
    // image to a full-width row (2 · 1 · 2 … like the app's gallery).
    $galleryRows = function ($imgs): array {
        $list = collect($imgs)->values()->all();
        $n = count($list);
        if ($n === 0) return [];
        if ($n === 1) return [[$list[0]]];
        $rows = [[array_shift($list), array_shift($list)]];
        if ($n % 2 === 1) $rows[] = [array_shift($list)];
        while ($list !== []) $rows[] = array_splice($list, 0, 2);
        return $rows;
    };

    // Preview: first 10 amenities (Airbnb shows 10).
    $previewAmenities = $amenities->take(10);

    $description = trim((string) $place->localized_description);

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
            window.calmTrack?.('photo', 'open', { place_id: @js($place->id) });
            // No section key = the «view all photos» tile rather than a jump
            // into one attribute's photos.
            if (!sectionKey) window.calmTrack?.('click', 'view_all', { section: 'photos' });
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
        openSheet(name) {
            this.sheet = name;
            // Sheet names already match the spec's section labels.
            window.calmTrack?.('click', 'view_all', { section: name });
            document.body.style.overflow = 'hidden';
        },
        closeSheet() { this.sheet = null; if (!this.gallery && !this.description) document.body.style.overflow = ''; },
        openDescription() {
            this.description = true;
            window.calmTrack?.('click', 'view_all', { section: 'description' });
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                const scroller = document.getElementById('description-scroll');
                if (scroller) scroller.scrollTo({ top: 0, behavior: 'instant' });
            });
        },
        closeDescription() { this.description = false; if (!this.gallery && !this.sheet) document.body.style.overflow = ''; },
        liked: {{ ($viewerLiked ?? false) ? 'true' : 'false' }},
        signedIn: {{ auth('api')->check() ? 'true' : 'false' }},
        toggleLike() {
            if (!this.signedIn) { window.dispatchEvent(new CustomEvent('calm-open-login')); return; }
            this.liked = !this.liked;
            window.calmTrack?.('click', 'like', { place_id: @js($place->id), liked: this.liked });
            fetch('/api/places/{{ $place->id }}/like', { method: this.liked ? 'POST' : 'DELETE', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).catch(() => {});
        },
        shareCopied: false,
        goBack() {
            // Never history.back(): after returning from payment the previous
            // entry can be Moyasar. Same-origin results/list referrers keep
            // their search context; anything else lands on home.
            try {
                const ref = document.referrer ? new URL(document.referrer) : null;
                if (ref && ref.origin === window.location.origin
                    && (ref.pathname === '/' || ref.pathname.startsWith('/lists'))) {
                    window.location.href = ref.href;
                    return;
                }
            } catch (e) { /* fall through */ }
            window.location.href = '{{ route('landing') }}';
        },
        sharePlace() {
            window.calmTrack?.('click', 'share', { place_id: @js($place->id) });
            // Clean listing URL (no ?check_in etc.).
            const url = window.location.origin + window.location.pathname;
            // navigator.share/clipboard exist only in secure contexts (https or
            // localhost) — over plain LAN http both are undefined, so fall all
            // the way back to the execCommand copy.
            if (navigator.share) { navigator.share({ title: document.title, url }).catch(() => {}); return; }
            const done = () => { this.shareCopied = true; setTimeout(() => this.shareCopied = false, 2200); };
            const legacy = () => {
                const ta = document.createElement('textarea');
                ta.value = url;
                ta.style.cssText = 'position: fixed; top: 0; opacity: 0;';
                document.body.appendChild(ta);
                ta.focus(); ta.select();
                try { document.execCommand('copy'); } catch (e) { /* no-op */ }
                document.body.removeChild(ta);
                done();
            };
            if (navigator.clipboard?.writeText) { navigator.clipboard.writeText(url).then(done).catch(legacy); }
            else { legacy(); }
        },
    }"
    @keydown.escape.window="closeGallery(); closeSheet(); closeDescription();"
    {{-- view:place fires on the PAGE, not on the card that led here, so shared
         WhatsApp links and direct URLs are captured too (docs/feature-analytics.md). --}}
    x-init="window.calmTrack?.('view', 'place', {
        place_id: @js($place->id),
        price_sar: {{ (int) $place->price }},
        source: window.calmSource?.(),
    })"
>
    <style>
        /* Mobile: the content column becomes an app-style sheet rising over
           the hero carousel with rounded top corners. Unlayered on purpose so
           it beats Tailwind's layered w-full inside the media query. */
        .calm-place-sheet { padding-top: 26px; max-width: 720px; }

        /* Desktop (web view): title bar above the photos, then a two-column
           body — content beside a sticky booking box. */
        .calm-place-titlebar { display: none; }
        .calm-place-book { display: none; }
        @media (min-width: 1024px) {
            .calm-place-titlebar { display: flex; }
            .calm-place-cols { display: flex; align-items: flex-start; gap: 44px; }
            .calm-place-content { flex: 1 1 0%; min-width: 0; max-width: none; }
            .calm-place-book {
                display: block;
                flex: 0 0 360px;
                width: 360px;
                position: sticky;
                top: 116px; /* clears the sticky site header */
                margin-top: 26px;
            }
        }

        @media (max-width: 639px) {
            .calm-place-sheet {
                position: relative;
                z-index: 1;
                width: calc(100% + 48px);
                margin: -30px -24px 0;
                background: #ffffff;
                border-radius: 28px 28px 0 0;
                corner-shape: squircle;
                -webkit-corner-shape: squircle;
                padding: 30px 24px 0;
            }
        }
    </style>

    {{-- Share fallback feedback --}}
    <div x-show="shareCopied" x-cloak x-transition.opacity
         class="fixed z-50 text-white font-bold {{ $fa }}"
         style="top: calc(70px + env(safe-area-inset-top)); left: 50%; transform: translateX(-50%); background: rgba(25,25,25,0.9); padding: 10px 20px; border-radius: 999px; font-size: 13px; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); white-space: nowrap;">
        {{ $isRtl ? 'تم نسخ الرابط' : 'Link copied' }}
    </div>
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

    {{-- HEADER — on mobile the app shows none (the hero starts at the very top
         with floating back/share/like circles). Tablet keeps the old topbar;
         desktop wears the same site header as the home page. --}}
    <div class="hidden sm:block lg:hidden">
        @include('partials._web_topbar')
    </div>
    @include('partials._web_desktop_header')

    {{-- MOBILE FLOATING HEADER — back/share/like stay FIXED while scrolling;
         a white blurred bar fades in once the sheet slides under them.
         pointer-events are inline styles on purpose: the Tailwind
         `pointer-events-auto` class wasn't in the build, which is what made
         these buttons dead the first time. --}}
    <div class="sm:hidden fixed inset-x-0 top-0" style="z-index: 40; pointer-events: none;"
         x-data="{ sc: false, th: 220 }"
         x-init="th = Math.max(160, Math.round(window.innerHeight * 0.58) - 90)"
         @scroll.window.passive="sc = window.scrollY > th;
            // end:place — they read the listing all the way down. Once a session.
            if (window.scrollY + window.innerHeight >= document.body.scrollHeight - 80) {
                window.calmTrackOnce?.('end', 'place', { place_id: @js($place->id) });
            }">
        <div class="absolute inset-0"
             :style="'transition: opacity 0.25s; border-bottom: 1px solid #F1F1F1; background-color: rgba(255,255,255,0.92); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); opacity: ' + (sc ? 1 : 0) + ';'"></div>
        <div class="relative flex items-center justify-between" style="padding: 10px 16px; padding-top: calc(10px + env(safe-area-inset-top));">
            <button type="button" @click="goBack()"
                    class="calm-round calm-press flex items-center justify-center text-black"
                    style="pointer-events: auto; width: 40px; height: 40px; border-radius: 50%; background-color: rgba(255,255,255,0.92); box-shadow: 0 2px 12px rgba(0,0,0,0.14); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);"
                    aria-label="{{ $isRtl ? 'رجوع' : 'Back' }}">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="{{ $isRtl ? '' : 'transform: scaleX(-1);' }}">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="flex items-center" style="gap: 10px;">
                <button type="button" @click="sharePlace()"
                        class="calm-round calm-press flex items-center justify-center text-black"
                        style="pointer-events: auto; width: 40px; height: 40px; border-radius: 50%; background-color: rgba(255,255,255,0.92); box-shadow: 0 2px 12px rgba(0,0,0,0.14); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);"
                        aria-label="{{ $isRtl ? 'مشاركة' : 'Share' }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line>
                    </svg>
                </button>
                <button type="button" @click="toggleLike()"
                        class="calm-round calm-press-like flex items-center justify-center"
                        style="pointer-events: auto; width: 40px; height: 40px; border-radius: 50%; background-color: rgba(255,255,255,0.92); box-shadow: 0 2px 12px rgba(0,0,0,0.14); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);"
                        aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" :fill="liked ? '#F88379' : 'none'" :stroke="liked ? '#F88379' : '#1A1A1A'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto w-full px-6 sm:px-10 lg:px-20 pt-0 sm:pt-10" style="padding-bottom: 110px;">

        {{-- DESKTOP TITLE ROW — above the photos. Follows the page direction:
             title at the start (right in Arabic, left in English) with share +
             like at the end. --}}
        <div class="calm-place-titlebar items-center justify-between" style="gap: 20px; padding-bottom: 16px;">
            <h1 class="font-bold text-black truncate {{ $fa }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" style="font-size: 26px; line-height: 1.3;">
                {{ $place->localized_title ?: $placeLabel }}
            </h1>
            <div class="shrink-0 flex items-center" style="gap: 6px;">
                <button type="button" @click="sharePlace()"
                        class="calm-press inline-flex items-center font-semibold text-black hover:bg-[#F5F5F5] transition-colors {{ $fa }}"
                        style="gap: 8px; padding: 9px 14px; border-radius: 12px; font-size: 14px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line>
                    </svg>
                    <span>{{ $isRtl ? 'مشاركة' : 'Share' }}</span>
                </button>
                <button type="button" @click="toggleLike()"
                        class="calm-press-like inline-flex items-center font-semibold text-black hover:bg-[#F5F5F5] transition-colors {{ $fa }}"
                        style="gap: 8px; padding: 9px 14px; border-radius: 12px; font-size: 14px;"
                        aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}">
                    <svg width="17" height="17" viewBox="0 0 24 24" :fill="liked ? '#F88379' : 'none'" :stroke="liked ? '#F88379' : '#1A1A1A'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span x-text="liked ? '{{ $isRtl ? 'محفوظ' : 'Saved' }}' : '{{ $isRtl ? 'حفظ' : 'Save' }}'"></span>
                </button>
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

            {{-- MOBILE HERO — full-bleed app-style carousel. Dots + counter ON
                 the image; floating back/share/like circles; the content sheet
                 below overlaps it with rounded top corners.
                 Slides are plain divs, NOT <button>s: iOS Safari won't reliably
                 start a scroll gesture from a button, which is why touch swipe
                 felt dead in the previous version. --}}
            <div class="sm:hidden relative" style="margin-inline: -24px;"
                 x-data="{
                    idx: 0, total: {{ $imgCount }},
                    onScroll(el) {
                        const w = el.clientWidth;
                        if (w) this.idx = Math.max(0, Math.min(this.total - 1, Math.round(Math.abs(el.scrollLeft) / w)));
                        // Binary flags: they looked at the photos, and they saw
                        // them all. Both once per session.
                        window.calmTrackOnce?.('scroll', 'photos', { place_id: @js($place->id), surface: 'detail' });
                        if (this.idx >= this.total - 1) {
                            window.calmTrackOnce?.('end', 'photos', { place_id: @js($place->id) });
                        }
                    },
                 }">
                <div class="flex calm-hide-scroll" dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
                     style="overflow-x: auto; overflow-y: hidden; scroll-snap-type: x mandatory; height: 58vh; min-height: 340px; max-height: 540px; background-color: #f7f7f7; touch-action: pan-x pan-y;"
                     @scroll.passive="onScroll($event.target)">
                    @foreach($heroImages as $i => $img)
                        <div @click="openGallery('{{ $sectionKeyFor($img) }}')"
                             style="width: 100%; height: 100%; flex-shrink: 0; scroll-snap-align: center; scroll-snap-stop: always;">
                            <img src="{{ $img->url }}" style="width: 100%; height: 100%; object-fit: cover; pointer-events: none;" alt="" draggable="false" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                        </div>
                    @endforeach
                </div>

                {{-- Indicators ON the image (above the sheet overlap). --}}
                @if($imgCount > 1)
                    @if($imgCount <= 12)
                        <div class="absolute flex items-center justify-center pointer-events-none"
                             style="left: 0; right: 0; bottom: 48px; gap: 5px;">
                            @foreach($heroImages as $i => $img)
                                <span class="block"
                                      :style="'height: 5px; border-radius: 3px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3); transition: all 0.25s; ' + (idx === {{ $i }} ? 'width: 14px; opacity: 1;' : 'width: 5px; opacity: 0.55;')"></span>
                            @endforeach
                        </div>
                    @endif
                    <div class="absolute text-white font-bold pointer-events-none tabular-nums" dir="ltr"
                         style="left: 16px; bottom: 42px; font-size: 11px; background-color: rgba(0,0,0,0.45); padding: 4px 10px; border-radius: 999px; backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);">
                        <span x-text="idx + 1"></span> / {{ $imgCount }}
                    </div>
                @endif
            </div>
        @endif

        {{-- ══ App-parity content (mobile-app layout) ══ --}}
        @php
            // 12h clock like the app («2:00 AM»).
            $fmtTime = function (?string $t): string {
                if (! $t) return '';
                [$h, $m] = explode(':', $t);
                $h = (int) $h;
                return ($h % 12 ?: 12).':'.$m.' '.($h < 12 ? 'AM' : 'PM');
            };
            $facilitiesWithPhotos = $facilities->filter(fn ($pa) => $facilityImages($pa)->isNotEmpty())->values();
            $galleryFirstKey = $facilitiesWithPhotos->isNotEmpty()
                ? 'attr-'.$facilitiesWithPhotos->first()->attribute_id
                : 'extras';
            $ratingAvg = $place->published_reviews_avg_rate !== null ? round((float) $place->published_reviews_avg_rate, 1) : 0.0;
            $ratingCount = (int) ($place->published_reviews_count ?? 0);
        @endphp
        {{-- Desktop: content column + sticky booking box. One column on
             mobile/tablet, where the app sheet + fixed reserve bar stay. --}}
        <div class="calm-place-cols">
        <div class="calm-place-sheet calm-place-content mx-auto w-full {{ $start }}">

            {{-- Title — desktop shows it above the photos instead --}}
            <h1 class="calm-hide-desktop text-center font-bold text-black {{ $fa }}" style="font-size: 26px; line-height: 1.3;">
                {{ $place->localized_title ?: $placeLabel }}
            </h1>

            {{-- Stats row: guests | rating | city/area --}}
            <div class="flex items-stretch" style="max-width: 560px; margin: 20px auto 0;">
                <div class="flex-1 text-center">
                    <div class="font-bold text-black tabular-nums" style="font-size: 19px;">{{ (int) $place->max_guests }}</div>
                    <div class="{{ $fa }}" style="font-size: 12px; color: #AAAAAA; margin-top: 2px;">{{ $isRtl ? 'ضيف' : 'guests' }}</div>
                </div>
                <span class="shrink-0" style="width: 1px; background: #F1F1F1;"></span>
                <div class="flex-1 text-center">
                    <div class="font-bold text-black tabular-nums" style="font-size: 19px;">{{ number_format($ratingAvg, 1) }}</div>
                    <div dir="ltr" style="font-size: 11px; letter-spacing: 1.5px; margin-top: 1px;">
                        @for($st = 1; $st <= 5; $st++)<span style="color: {{ $st <= round($ratingAvg) ? '#F5B60F' : '#E3E3E3' }};">★</span>@endfor
                    </div>
                    <div class="{{ $fa }}" style="font-size: 12px; color: #AAAAAA; margin-top: 1px;">{{ $ratingCount }} {{ $isRtl ? 'تقييم' : 'reviews' }}</div>
                </div>
                <span class="shrink-0" style="width: 1px; background: #F1F1F1;"></span>
                <div class="flex-1 text-center">
                    <div class="font-bold text-black {{ $fa }}" style="font-size: 15px; line-height: 1.5;">{{ $isRtl ? $place->cityArea?->city?->name_ar : $place->cityArea?->city?->name_en }}</div>
                    <div class="{{ $fa }}" style="font-size: 12px; color: #AAAAAA; margin-top: 2px;">{{ $isRtl ? $place->cityArea?->name_ar : $place->cityArea?->name_en }}</div>
                </div>
            </div>

            {{-- وصف --}}
            @if($description !== '')
                <section style="margin-top: 44px;">
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 20px;">{{ $isRtl ? 'وصف' : 'Description' }}</h2>
                    <p class="text-black {{ $fa }}" style="font-size: 15px; line-height: 1.9; margin-top: 14px; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 6; -webkit-box-orient: vertical; overflow: hidden;">{{ $description }}</p>
                    @if(mb_strlen($description) > 220)
                        <button type="button" @click="openDescription()"
                                class="calm-press font-bold text-black underline {{ $fa }}" style="margin-top: 10px; font-size: 14px;">
                            {{ $isRtl ? 'عرض المزيد' : 'Show more' }}
                        </button>
                    @endif
                </section>
            @endif

            {{-- الإقامة — facility rows with count badges --}}
            @if($facilities->isNotEmpty())
                <section style="margin-top: 44px;">
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 20px;">{{ $isRtl ? 'الإقامة' : 'The stay' }}</h2>
                    <div style="margin-top: 8px;">
                        @foreach($facilities as $pa)
                            <div class="flex items-start justify-between" style="padding: 15px 0; gap: 12px;">
                                <div class="min-w-0">
                                    <div class="flex items-center" style="gap: 10px;">
                                        <span style="font-size: 20px; line-height: 1;">{{ $pa->attribute->icon ?: '•' }}</span>
                                        <span class="font-bold text-black {{ $fa }}" style="font-size: 16px;">{{ $isRtl ? $pa->attribute->name_ar : $pa->attribute->name_en }}</span>
                                    </div>
                                    @if($pa->description)
                                        <div class="{{ $fa }}" style="font-size: 13px; color: #AAAAAA; margin-top: 6px;">{{ $pa->description }}</div>
                                    @endif
                                </div>
                                <span class="calm-round shrink-0 flex items-center justify-center font-bold text-black tabular-nums"
                                      style="width: 34px; height: 34px; border-radius: 50%; background-color: #F5F5F5; font-size: 13px;">{{ (int) $pa->value }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- صور المرافق — photo-group cards + «عرض كل الصور» --}}
            @if($facilitiesWithPhotos->isNotEmpty())
                <section style="margin-top: 44px;">
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 20px;">{{ $isRtl ? 'صور المرافق' : 'Space photos' }}</h2>
                    <div class="flex overflow-x-auto calm-hide-scroll" style="gap: 14px; margin-top: 16px; padding: 4px 2px 8px;">
                        @foreach($facilitiesWithPhotos as $fi => $pa)
                            @php $fImgs = $facilityImages($pa); @endphp
                            <button type="button" @click="openGallery('attr-{{ $pa->attribute_id }}')" class="calm-press-card shrink-0 text-start">
                                <span class="block overflow-hidden bg-[#F3F4F6]"
                                      style="width: {{ $fi === 0 ? '300px' : '150px' }}; height: 150px; border-radius: 20px; corner-shape: squircle; -webkit-corner-shape: squircle;">
                                    <img src="{{ $fImgs->first()->url }}" alt="" loading="lazy" class="w-full h-full object-cover">
                                </span>
                                <span class="block font-bold text-black {{ $fa }}" style="font-size: 14px; margin-top: 9px;">{{ $isRtl ? $pa->attribute->name_ar : $pa->attribute->name_en }}</span>
                                @if($pa->description)
                                    <span class="block truncate {{ $fa }}" style="font-size: 12px; color: #AAAAAA; margin-top: 2px; max-width: {{ $fi === 0 ? '300px' : '150px' }};">{{ $pa->description }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                    <button type="button" @click="openGallery('{{ $galleryFirstKey }}')"
                            class="calm-press w-full font-bold text-black {{ $fa }}"
                            style="margin-top: 14px; padding: 15px; border-radius: 18px; background-color: #F5F5F5; font-size: 14px;">
                        {{ $isRtl ? 'عرض كل الصور' : 'View all photos' }} ({{ $totalImages }})
                    </button>
                </section>
            @endif

            {{-- المميزات البارزة — highlighted amenities, start-aligned like the app --}}
            @if($highlightedAmenities->isNotEmpty())
                <section style="margin-top: 44px; border-top: 1px solid #F1F1F1; border-bottom: 1px solid #F1F1F1; padding: 30px 0 34px;">
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 20px;">{{ $isRtl ? 'المميزات البارزة' : 'Highlights' }}</h2>
                    <div class="flex items-start justify-center" style="margin-top: 22px; gap: 28px;">
                        @foreach($highlightedAmenities->take(3) as $pa)
                            <div class="text-center shrink-0" style="width: 94px;">
                                <span class="calm-round inline-flex items-center justify-center bg-white"
                                      style="width: 64px; height: 64px; border-radius: 50%; box-shadow: 0 0 22px rgba(0,0,0,0.08); font-size: 24px;">{{ $pa->attribute->icon ?: '✨' }}</span>
                                <div class="font-bold text-black {{ $fa }}" style="font-size: 13px; margin-top: 10px; line-height: 1.5;">{{ $isRtl ? $pa->attribute->name_ar : $pa->attribute->name_en }}</div>
                                @if($pa->description)
                                    <div class="{{ $fa }}" style="font-size: 11.5px; color: #AAAAAA; margin-top: 2px;">{{ $pa->description }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- المرافق و المميزات — first 5 + show-all sheet --}}
            @if($amenities->isNotEmpty())
                <section style="margin-top: 44px;">
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 20px;">{{ $isRtl ? 'المرافق و المميزات' : 'Amenities & features' }}</h2>
                    <div style="margin-top: 8px;">
                        @foreach($amenities->take(5) as $pa)
                            <div class="flex items-center" style="padding: 13px 0; gap: 12px;">
                                <span style="font-size: 20px; line-height: 1;">{{ $pa->attribute->icon ?: '•' }}</span>
                                <span class="font-semibold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? $pa->attribute->name_ar : $pa->attribute->name_en }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if($amenities->count() > 5)
                        <button type="button" @click="openSheet('amenities')"
                                class="calm-press w-full font-bold text-black {{ $fa }}"
                                style="margin-top: 10px; padding: 15px; border-radius: 18px; background-color: #F5F5F5; font-size: 14px;">
                            {{ $isRtl ? 'عرض جميع المرافق' : 'Show all amenities' }} ({{ $amenities->count() }})
                        </button>
                    @endif
                </section>
            @endif

            {{-- أوقات الدخول والمغادرة --}}
            <section style="margin-top: 44px;">
                <h2 class="font-bold text-black {{ $fa }}" style="font-size: 20px;">{{ $isRtl ? 'أوقات الدخول والمغادرة' : 'Check-in & check-out' }}</h2>
                <div class="flex items-center bg-white" style="margin-top: 16px; border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 50px rgba(0,0,0,0.06); padding: 26px 18px; gap: 8px;">
                    <div class="flex-1 text-center min-w-0">
                        <div class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'الدخول' : 'Check-in' }}</div>
                        <div class="font-bold text-black tabular-nums" dir="ltr" style="font-size: 24px; margin-top: 6px;">{{ $fmtTime($place->check_in_time) }}</div>
                        <div class="{{ $fa }}" style="font-size: 12px; color: #AAAAAA; margin-top: 6px;">{{ $isRtl ? 'فى أول يوم من الحجز' : "On the booking's first day" }}</div>
                    </div>
                    <span class="calm-round shrink-0 flex items-center justify-center" style="width: 44px; height: 44px; border-radius: 50%; background-color: #F5F5F5;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 10l-4-3 4-3M3 7h13a5 5 0 0 1 5 5M17 14l4 3-4 3M21 17H8a5 5 0 0 1-5-5"></path>
                        </svg>
                    </span>
                    <div class="flex-1 text-center min-w-0">
                        <div class="{{ $fa }}" style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'المغادرة' : 'Check-out' }}</div>
                        <div class="font-bold text-black tabular-nums" dir="ltr" style="font-size: 24px; margin-top: 6px;">{{ $fmtTime($place->check_out_time) }}</div>
                        <div class="{{ $fa }}" style="font-size: 12px; color: #AAAAAA; margin-top: 6px;">
                            {{ $place->checkout_next_day
                                ? ($isRtl ? 'فى اليوم التالى لآخر يوم من الحجز' : 'The morning after the last booked day')
                                : ($isRtl ? 'فى آخر يوم من الحجز' : 'On the last booked day') }}
                        </div>
                    </div>
                </div>
            </section>

            {{-- التقييمات — app parity: centred average with black stars, then
                 a horizontal strip of reviews separated by vertical hairlines
                 (no cards). «عرض جميع التقييمات» opens the modal. --}}
            @if($place->publishedReviews->isNotEmpty())
                <section style="margin-top: 44px; border-top: 1px solid #F1F1F1; padding-top: 34px;">
                    <div class="text-center">
                        <div class="font-bold text-black tabular-nums" style="font-size: 42px; line-height: 1;">{{ number_format($ratingAvg, 1) }}</div>
                        <div dir="ltr" style="font-size: 15px; letter-spacing: 3px; margin-top: 8px;">
                            @for($st = 1; $st <= 5; $st++)<span style="color: {{ $st <= round($ratingAvg) ? '#1A1A1A' : '#E3E3E3' }};">★</span>@endfor
                        </div>
                        <div class="{{ $fa }}" style="font-size: 13px; color: #AAAAAA; margin-top: 6px;">{{ $ratingCount }} {{ $isRtl ? 'تقييم' : 'reviews' }}</div>
                    </div>
                    <div class="flex items-stretch overflow-x-auto calm-hide-scroll" style="margin-top: 30px;">
                        @foreach($place->publishedReviews->take(6) as $review)
                            <div class="shrink-0" style="width: min(72vw, 300px);{{ $loop->first ? ' padding-inline-end: 18px;' : ' padding-inline: 18px; border-inline-start: 1px solid #F1F1F1;' }}">
                                @include('partials._web_review_item', ['review' => $review, 'clamp' => true])
                            </div>
                        @endforeach
                    </div>
                    <button type="button" @click="openSheet('reviews')"
                            class="calm-press w-full font-bold text-black {{ $fa }}"
                            style="margin-top: 24px; padding: 15px; border-radius: 18px; background-color: #F5F5F5; font-size: 14px;">
                        {{ $isRtl ? 'عرض جميع التقييمات' : 'Show all reviews' }} ({{ $ratingCount }})
                    </button>
                </section>
            @endif

            {{-- تعليمات هامة و القواعد --}}
            @if($place->localized_rules)
                <section style="margin-top: 44px; padding-bottom: 20px;">
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 20px;">{{ $isRtl ? 'تعليمات هامة و القواعد' : 'Important instructions & rules' }}</h2>
                    <p class="text-black {{ $fa }}" style="font-size: 15px; line-height: 2; margin-top: 14px; white-space: pre-line;">{{ $place->localized_rules }}</p>
                </section>
            @endif
        </div>{{-- /.calm-place-content --}}

        {{-- ── Desktop booking box — sticky beside the content: price, inline
             month picker and the reserve CTA. Replaces the fixed bottom bar,
             which is mobile/tablet only now. ── --}}
        @if($place->isVisible())
            <aside class="calm-place-book">
                <div x-data="calmBookingBox(@js([
                        'placeId' => $place->id,
                        'checkoutUrl' => route('book.checkout', $place),
                        'isRtl' => $isRtl,
                     ]))"
                     class="bg-white {{ $start }}"
                     style="border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 50px rgba(0,0,0,0.05); padding: 20px;">

                    <div class="flex items-baseline" style="gap: 6px;">
                        <span class="font-bold text-black tabular-nums" style="font-size: 22px;"><bdi dir="ltr">{{ number_format((int) $place->price) }} SR</bdi></span>
                        <span class="{{ $fa }}" style="font-size: 13px; color: #AAAAAA;">{{ $isRtl ? 'لليلة الواحدة' : 'per night' }}</span>
                    </div>

                    {{-- Month navigation --}}
                    <div class="flex items-center justify-between" style="margin-top: 18px;">
                        <button type="button" @click="prevMonth()" :disabled="mi === 0"
                                class="calm-press calm-round flex items-center justify-center text-black"
                                :style="'width: 32px; height: 32px; border-radius: 50%; opacity: ' + (mi === 0 ? 0.3 : 1) + ';'"
                                aria-label="{{ $isRtl ? 'الشهر السابق' : 'Previous month' }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                        <span class="font-bold text-black {{ $fa }}" style="font-size: 15px;" x-text="months[mi] ? months[mi].label : ''"></span>
                        <button type="button" @click="nextMonth()" :disabled="mi >= months.length - 1"
                                class="calm-press calm-round flex items-center justify-center text-black"
                                :style="'width: 32px; height: 32px; border-radius: 50%; opacity: ' + (mi >= months.length - 1 ? 0.3 : 1) + ';'"
                                aria-label="{{ $isRtl ? 'الشهر التالي' : 'Next month' }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    </div>

                    {{-- Weekday header --}}
                    <div class="grid grid-cols-7 text-center" style="margin-top: 10px;">
                        <template x-for="(d, i) in dayNames()" :key="'bd' + i">
                            <span style="font-size: 11px; color: #AAAAAA;" x-text="d"></span>
                        </template>
                    </div>

                    {{-- Days --}}
                    <template x-if="months[mi]">
                        <div class="grid grid-cols-7" style="margin-top: 2px;">
                            <template x-for="cell in months[mi].cells" :key="months[mi].key + '-' + cell.key">
                                <div class="flex items-center justify-center" :style="cellStyle(cell).replace('height: 46px', 'height: 40px')">
                                    <button type="button" x-show="cell.day" :disabled="cell.disabled"
                                            @click="pickDay(cell.date)"
                                            class="flex items-center justify-center tabular-nums"
                                            :style="dayStyle(cell).replace('width: 40px; height: 40px', 'width: 34px; height: 34px').replace('font-size: 15px', 'font-size: 13px')"
                                            x-text="cell.day"></button>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div class="text-center {{ $fa }}" style="font-size: 13px; margin-top: 10px; color: #AAAAAA;"
                         x-text="hasRange() ? rangeTitle() : '{{ $isRtl ? 'اختر تاريخ الوصول والمغادرة' : 'Pick your arrival and departure' }}'"></div>
                    <p x-show="rangeError" x-cloak class="text-center {{ $fa }}" style="font-size: 12.5px; color: #dc2626; margin-top: 6px;" x-text="rangeError"></p>

                    <button type="button" @click="window.calmTrack?.('click', 'reserve', { place_id: @js($place->id), price_sar: {{ (int) $place->price }} }); next()" :disabled="!ready()"
                            class="calm-press w-full font-medium text-white {{ $fa }}"
                            :style="'margin-top: 14px; padding: 15px; border-radius: 15px; font-size: 15px; transition: opacity 0.2s; background-color: #F88379; box-shadow: 0 6px 12px rgba(248,131,121,0.3); opacity: ' + (ready() ? 1 : 0.45) + ';'">
                        {{ $isRtl ? 'احجز الآن' : 'Reserve' }}
                    </button>
                    <p class="text-center {{ $fa }}" style="margin-top: 8px; font-size: 11.5px; color: #AAAAAA;">
                        {{ $isRtl ? 'لن يتم خصم أي مبلغ الآن' : "You won't be charged yet" }}
                    </p>
                </div>
            </aside>
        @endif
        </div>{{-- /.calm-place-cols --}}

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

    {{-- ─────────── RESERVE BAR — the web booking funnel entry ───────────
         Spec §5.5: blur + white 0.8 tint, 0 0 25px shadow, row PINNED LTR
         (price visually left, CTA right in both locales), price 17/22 bold,
         unit 12/16 muted, coral CTA radius 15 with colored shadow. --}}
    @if($place->isVisible())
        {{-- Mobile/tablet only — desktop reserves from the sticky booking box. --}}
        <div class="calm-hide-desktop fixed inset-x-0 bottom-0 z-30"
             style="background-color: rgba(255,255,255,0.8); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 0 25px rgba(0,0,0,0.05);">
            <div class="mx-auto flex items-center" dir="ltr" style="max-width: 1200px; padding: 16px 20px 14px; gap: 12px;">
                <div class="flex-1 min-w-0 text-left">
                    <div class="font-bold text-black tabular-nums" style="font-size: 20px; line-height: 24px;"><bdi dir="ltr">{{ number_format((int) $place->price) }} SR</bdi></div>
                    <div class="{{ $fa }}" style="font-size: 12px; line-height: 16px; margin-top: 3px; color: #AAAAAA;">{{ $isRtl ? 'لليلة الواحدة' : 'per night' }}</div>
                </div>
                <button type="button" @click="window.calmTrack?.('click', 'reserve', { place_id: @js($place->id), price_sar: {{ (int) $place->price }} }); window.dispatchEvent(new CustomEvent('calm-open-booking'))"
                        class="calm-press inline-flex items-center justify-center font-medium text-white bg-[#F88379] hover:bg-[#E66E64] transition-colors {{ $fa }}"
                        style="padding: 14px 48px; border-radius: 15px; font-size: 15px; line-height: 20px; box-shadow: 0 6px 12px rgba(248,131,121,0.3);">
                    {{ $isRtl ? 'احجز الآن' : 'Reserve' }}
                </button>
            </div>
        </div>
    @endif

    {{-- ─────────── BOOKING MODAL — dates → summary → pay/login ─────────── --}}
    @if($place->isVisible())
        @include('partials._web_booking_modal')
    @endif

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

    {{-- ─────────── ALL AMENITIES MODAL — grouped cards with count badges ─────────── --}}
    <div x-show="sheet === 'amenities'" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.5);" @click="closeSheet()" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:max-w-2xl sm:h-fit bg-white flex flex-col overflow-hidden"
             style="border-radius: 28px 28px 0 0; corner-shape: squircle; max-height: calc(100% - 56px);"
             x-show="sheet === 'amenities'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
             dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
            <div class="relative flex items-center justify-center shrink-0" style="padding: 16px 20px 12px;">
                <button type="button" @click="closeSheet()" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                        class="calm-press calm-round absolute flex items-center justify-center bg-white text-black"
                        style="inset-inline-start: 16px; width: 42px; height: 42px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 16px;">✕</button>
                <h2 class="font-bold text-black {{ $fa }}" style="font-size: 18px;">{{ $isRtl ? 'كل المرافق و المميزات' : 'All amenities & features' }}</h2>
            </div>
            <div class="overflow-y-auto {{ $start }}"
                 style="padding: 14px 20px 40px; -webkit-overflow-scrolling: touch; overscroll-behavior: contain;">
                @foreach($groupedAll as $gi => $group)
                    <div style="{{ $gi > 0 ? 'margin-top: 28px;' : '' }}">
                        <h3 class="font-bold text-black {{ $fa }}" style="font-size: 18px;">{{ $group['label'] }}</h3>
                        <div class="bg-white" style="margin-top: 14px; border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 30px rgba(0,0,0,0.05); padding: 4px 18px;">
                            @foreach($group['items'] as $pa)
                                <div class="flex items-center justify-between" style="padding: 16px 0; gap: 12px;{{ $loop->last ? '' : ' border-bottom: 1px solid #F5F5F5;' }}">
                                    <div class="flex items-start min-w-0" style="gap: 12px;">
                                        <span style="font-size: 22px; line-height: 1.2;">{{ $pa->attribute->icon ?: '·' }}</span>
                                        <div class="min-w-0">
                                            <div class="font-bold text-black {{ $fa }}" style="font-size: 15px;">{{ $isRtl ? $pa->attribute->name_ar : $pa->attribute->name_en }}</div>
                                            @if($pa->description)
                                                <div class="{{ $fa }}" style="font-size: 12.5px; color: #AAAAAA; margin-top: 4px;">{{ $pa->description }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="calm-round shrink-0 flex items-center justify-center font-bold text-white tabular-nums"
                                          style="width: 26px; height: 26px; border-radius: 50%; background-color: #1A1A1A; font-size: 12px;">{{ (int) $pa->value }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ─────────── REVIEWS MODAL — histogram summary + hairline-separated list ─────────── --}}
    @if($place->publishedReviews->isNotEmpty())
        @php
            $revTotal = max(1, $place->publishedReviews->count());
            $revDist = [];
            for ($lv = 5; $lv >= 1; $lv--) {
                $revDist[$lv] = $place->publishedReviews->filter(fn ($r) => (int) round($r->rate) === $lv)->count();
            }
        @endphp
        <div x-show="sheet === 'reviews'" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.5);" @click="closeSheet()" x-transition.opacity></div>
            <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:max-w-2xl sm:h-fit bg-white flex flex-col overflow-hidden"
                 style="border-radius: 28px 28px 0 0; corner-shape: squircle; max-height: calc(100% - 56px);"
                 x-show="sheet === 'reviews'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
                 x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100"
                 x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
                 dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                <div class="relative flex items-center justify-center shrink-0" style="padding: 16px 20px 12px;">
                    <button type="button" @click="closeSheet()" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                            class="calm-press calm-round absolute flex items-center justify-center bg-white text-black"
                            style="inset-inline-start: 16px; width: 42px; height: 42px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.08); font-size: 16px;">✕</button>
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 18px;">
                        <bdi dir="ltr" class="tabular-nums">{{ number_format($ratingAvg, 1) }}</bdi> · {{ $ratingCount }} {{ $isRtl ? 'تقييم' : 'reviews' }}
                    </h2>
                </div>
                <div class="overflow-y-auto {{ $start }}"
                     style="padding: 14px 24px 40px; -webkit-overflow-scrolling: touch; overscroll-behavior: contain;">
                    {{-- Summary: rating histogram (start side) + big average --}}
                    <div class="flex items-center" style="gap: 24px; padding: 8px 2px 0;">
                        <div class="flex-1 flex flex-col" style="gap: 9px;">
                            @for($lv = 5; $lv >= 1; $lv--)
                                <div class="flex items-center" style="gap: 10px;">
                                    <span class="tabular-nums" style="font-size: 12px; color: #1A1A1A; width: 10px; text-align: center;">{{ $lv }}</span>
                                    <div class="flex-1" style="height: 5px; border-radius: 3px; background-color: #F1F1F1; overflow: hidden;">
                                        <div style="height: 100%; border-radius: 3px; background-color: #1A1A1A; width: {{ round($revDist[$lv] / $revTotal * 100) }}%;"></div>
                                    </div>
                                </div>
                            @endfor
                        </div>
                        <div class="text-center shrink-0" style="min-width: 96px;">
                            <div class="font-bold text-black tabular-nums" style="font-size: 40px; line-height: 1;">{{ number_format($ratingAvg, 1) }}</div>
                            <div dir="ltr" style="font-size: 13px; letter-spacing: 2px; margin-top: 8px;">
                                @for($st = 1; $st <= 5; $st++)<span style="color: {{ $st <= round($ratingAvg) ? '#1A1A1A' : '#E3E3E3' }};">★</span>@endfor
                            </div>
                            <div class="{{ $fa }}" style="font-size: 12px; color: #AAAAAA; margin-top: 5px;">{{ $ratingCount }} {{ $isRtl ? 'تقييم' : 'reviews' }}</div>
                        </div>
                    </div>
                    {{-- Reviews, separated by hairlines (no cards) --}}
                    <div style="margin-top: 22px; border-top: 1px solid #F1F1F1;">
                        @foreach($place->publishedReviews as $review)
                            <div style="padding: 22px 0;{{ $loop->last ? '' : ' border-bottom: 1px solid #F1F1F1;' }}">
                                @include('partials._web_review_item', ['review' => $review])
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ─────────── PHOTO TOUR GALLERY MODAL ─────────── --}}    {{-- ─────────── PHOTO TOUR GALLERY MODAL ─────────── --}}
    <div x-show="gallery" x-cloak x-transition.opacity id="gallery-scroll"
         style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 60; background-color: #ffffff; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch;"
         dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
        <div class="sticky top-0 border-b border-[#ebebeb]"
             style="z-index: 2; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
            <div class="max-w-7xl mx-auto flex items-center justify-between px-6 sm:px-10 lg:px-20 h-20">
                <button type="button" @click="closeGallery()"
                        class="w-10 h-10 flex items-center justify-center text-[#222] hover:bg-[#f7f7f7] transition-colors"
                        style="border-radius: 999px; corner-shape: squircle;" aria-label="{{ $isRtl ? 'رجوع' : 'Back' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="{{ $isRtl ? '' : 'transform: scaleX(-1);' }}">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
                <h2 class="text-[16px] sm:text-[18px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'جولة بالصور' : 'Photo tour' }}</h2>
                <div class="flex items-center">
                    <button type="button" @click="sharePlace()"
                            class="w-10 h-10 flex items-center justify-center text-[#222] hover:bg-[#f7f7f7] transition-colors"
                            style="border-radius: 999px; corner-shape: squircle;" aria-label="{{ $isRtl ? 'مشاركة' : 'Share' }}">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line>
                        </svg>
                    </button>
                    <button type="button" @click="toggleLike()"
                            class="w-10 h-10 flex items-center justify-center hover:bg-[#f7f7f7] transition-colors calm-press-like"
                            style="border-radius: 999px; corner-shape: squircle;" aria-label="{{ $isRtl ? 'إضافة إلى المفضلة' : 'Save to favorites' }}">
                        <svg width="19" height="19" viewBox="0 0 24 24" :fill="liked ? '#F88379' : 'none'" :stroke="liked ? '#F88379' : '#1A1A1A'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </button>
                </div>
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
                        <div class="mt-5 calm-gallery-grid">
                            @foreach($galleryRows($facImages) as $row)
                                <div class="flex" style="gap: 6px;{{ $loop->first ? '' : ' margin-top: 6px;' }}">
                                    @foreach($row as $img)
                                        <img src="{{ $img->url }}" class="block bg-[#f7f7f7]" alt="" loading="lazy"
                                             style="flex: 1 1 0; min-width: 0; {{ count($row) === 2 ? 'aspect-ratio: 1 / 1;' : 'aspect-ratio: 4 / 3;' }} object-fit: cover; border-radius: 0;">
                                    @endforeach
                                </div>
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
                    <div class="mt-5 calm-gallery-grid">
                        @foreach($galleryRows($extraImages) as $row)
                            <div class="flex" style="gap: 6px;{{ $loop->first ? '' : ' margin-top: 6px;' }}">
                                @foreach($row as $img)
                                    <img src="{{ $img->url }}" class="block bg-[#f7f7f7]" alt="" loading="lazy"
                                         style="flex: 1 1 0; min-width: 0; {{ count($row) === 2 ? 'aspect-ratio: 1 / 1;' : 'aspect-ratio: 4 / 3;' }} object-fit: cover; border-radius: 0;">
                                @endforeach
                            </div>
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
    /* Photo-tour grid: edge-to-edge on mobile, back inside the container on sm+. */
    .calm-gallery-grid { margin-inline: -24px; }
    @media (min-width: 640px) { .calm-gallery-grid { margin-inline: 0; } }
    [x-cloak] { display: none !important; }
    .calm-hide-scroll { scrollbar-width: none; }
    .calm-hide-scroll::-webkit-scrollbar { display: none; }
</style>
@endsection
