@extends('layouts.app')

@php
    use App\Support\Catalog;
    use Illuminate\Support\Str;

    $ogTitle       = ($host->title ?: Catalog::placeTypeLabel($host->place_type, app()->getLocale())) . ' · Calm';
    $ogDescription = $host->description
        ? Str::limit(strip_tags($host->description), 180)
        : (app()->getLocale() === 'ar'
            ? 'إقامة فاخرة من كالم'
            : 'A luxury stay on Calm');
    $ogImage = $host->images->first()?->url;
    $ogUrl   = url()->current();
@endphp

@section('title', 'Calm — ' . ($host->title ?: $host->slug))

@section('meta')
    <meta name="description" content="{{ $ogDescription }}">
    {{-- Open Graph (WhatsApp, Facebook, Slack, LinkedIn, etc.) --}}
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
    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    @if($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
@endsection

@section('body')
@php
    $locale = app()->getLocale();
    $isRtl  = $locale === 'ar';
    $fa     = $isRtl ? 'font-arabic' : '';
    $start  = $isRtl ? 'text-right' : 'text-left';

    $allImages   = $host->images->values();
    $extraImages = $host->images->whereNull('host_facility_id')->values();
    $placeLabel  = Catalog::placeTypeLabel($host->place_type, $locale);

    // map host_facility_id => facility key, for jumping into the right gallery section on click
    $facilityKeyById  = $host->facilities->pluck('key', 'id')->all();
    $sectionKeyFor    = fn ($img) => $img->host_facility_id
        ? ($facilityKeyById[$img->host_facility_id] ?? 'extras')
        : 'extras';

    // grouped amenities (used by full sheet AND inline preview)
    $groupedAmenities = [];
    foreach (Catalog::amenityGroups() as $group) {
        $items = [];
        foreach ($group['items'] as $cat) {
            if ($host->amenities->contains('key', $cat['key'])) {
                $items[] = $cat;
            }
        }
        if (! empty($items)) {
            $groupedAmenities[] = [
                'label' => $group[$locale] ?? $group['en'],
                'items' => $items,
            ];
        }
    }

    // preview: first 10 amenities (Airbnb shows 10)
    $previewAmenities = $host->amenities->take(10);

    // (legacy static fallbacks; only used when the host has no description saved)
    $descriptions = [
        'ar' => [
            'chalet'    => "شاليه فاخر في موقع مميز يجمع بين الهدوء والرفاهية. مساحات واسعة، تشطيبات راقية، وأجواء عائلية مثالية للإقامة المريحة.\n\nمكان مصمم بعناية ليمنحك تجربة استثنائية بعيداً عن صخب المدينة، مع كل ما تحتاجه من خدمات ووسائل راحة. غرف نوم مريحة، صالات جلوس واسعة، ومرافق خارجية للاستمتاع بأجمل اللحظات مع العائلة والأصدقاء.\n\nيتميز المكان بإطلالاته الخلابة وأجوائه الهادئة، مما يجعله الخيار الأمثل لقضاء إجازة لا تُنسى.",
            'resthouse' => "استراحة مجهزة بكل ما تحتاج لقضاء أوقات لا تُنسى مع العائلة والأصدقاء. مساحات خارجية فسيحة، أجواء هادئة، وتفاصيل تجعل كل لحظة مميزة.\n\nمكان مثالي للتجمعات والمناسبات الخاصة، يوفر الخصوصية والراحة في آنٍ واحد. مرافق متكاملة لضمان إقامة استثنائية.\n\nالموقع يجمع بين الفخامة والبساطة، مع تشطيبات راقية ومساحات مدروسة بعناية.",
            'camp'      => "تجربة فريدة في الهواء الطلق بأجواء أصيلة وإطلالات خلابة. مساحة للراحة والاسترخاء بعيداً عن صخب المدينة.\n\nاستمتع بإقامة لا تُنسى وسط الطبيعة، مع كل وسائل الراحة الحديثة في قلب البرية. خيام مجهزة، جلسات خارجية، وأجواء ساحرة.\n\nالمكان مثالي لمحبي المغامرات والباحثين عن السكون والهدوء.",
        ],
        'en' => [
            'chalet'    => "A luxurious chalet in a premier location, blending tranquility with refined comfort. Spacious areas, premium finishes, and an ideal family atmosphere for a relaxing stay.\n\nCarefully designed to give you an exceptional experience away from city noise — with every service and comfort you need. Comfortable bedrooms, spacious living areas, and outdoor facilities for memorable moments with family and friends.\n\nDistinguished by its stunning views and calm atmosphere, making it the ideal choice for an unforgettable getaway.",
            'resthouse' => "A fully-equipped resthouse with everything you need for memorable times with family and friends. Generous outdoor spaces, a calm setting, and thoughtful details that make every moment special.\n\nAn ideal place for gatherings and private occasions, offering privacy and comfort at once. Complete facilities to ensure an exceptional stay.\n\nThe location combines luxury with simplicity — refined finishes and carefully considered spaces.",
            'camp'      => "A unique outdoor experience with authentic vibes and stunning views. A space to rest and unwind, far from the city.\n\nEnjoy an unforgettable stay in nature with every modern comfort in the heart of the wild. Furnished tents, outdoor seating, and a magical atmosphere.\n\nThe perfect place for adventure-seekers and those craving silence and calm.",
        ],
    ];
    $description = trim((string) $host->description) !== ''
        ? $host->description
        : ($descriptions[$locale][$host->place_type] ?? '');

    // rules list
    $rules = $isRtl ? [
        ['emoji' => '🕒', 'text' => 'تسجيل الوصول من الساعة 3:00 عصراً'],
        ['emoji' => '🕛', 'text' => 'المغادرة قبل الساعة 12:00 ظهراً'],
        ['emoji' => '🚭', 'text' => 'يُمنع التدخين داخل المكان'],
        ['emoji' => '🔇', 'text' => 'ساعات الهدوء: 11 مساءً – 7 صباحاً'],
        ['emoji' => '🎉', 'text' => 'لا يُسمح بالحفلات الصاخبة'],
        ['emoji' => '🧹', 'text' => 'يُرجى المحافظة على نظافة المكان'],
    ] : [
        ['emoji' => '🕒', 'text' => 'Check-in from 3:00 PM'],
        ['emoji' => '🕛', 'text' => 'Check-out before 12:00 PM'],
        ['emoji' => '🚭', 'text' => 'No smoking indoors'],
        ['emoji' => '🔇', 'text' => 'Quiet hours: 11 PM – 7 AM'],
        ['emoji' => '🎉', 'text' => 'No loud parties'],
        ['emoji' => '🧹', 'text' => 'Please keep the place clean'],
    ];

    $totalImages = $host->images->count();
    $createdLabel = $host->created_at?->locale($locale)->translatedFormat('F Y');
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
                {{ $host->title ?? $placeLabel }}
            </h1>
            <div class="flex flex-wrap items-center justify-center {{ $fa }}"
                 style="gap: 8px; margin-top: 12px;">
                <span class="text-[13px] text-[#717171] bg-[#fafafa]"
                      style="padding: 4px 12px; border-radius: 999px; corner-shape: squircle;">
                    {{ $placeLabel }}
                </span>
                @if($host->max_guests)
                    <span class="text-[13px] text-[#717171] bg-[#fafafa]"
                          style="padding: 4px 12px; border-radius: 999px; corner-shape: squircle;">
                        {{ $host->max_guests }} {{ $isRtl ? 'ضيف' : 'guests' }}
                    </span>
                @endif
                @if($host->facilities->count() > 0)
                    <span class="text-[13px] text-[#717171] bg-[#fafafa]"
                          style="padding: 4px 12px; border-radius: 999px; corner-shape: squircle;">
                        {{ $host->facilities->count() }} {{ $isRtl ? 'مرافق' : 'facilities' }}
                    </span>
                @endif
            </div>
        </div>

        {{-- AIRBNB-STYLE MOSAIC (desktop) / SCROLL CAROUSEL (mobile) --}}
        @if($allImages->count() > 0)
            @php $imgCount = $allImages->count(); @endphp
            {{-- Desktop mosaic — layout adapts to image count --}}
            <div class="hidden sm:block relative">

                @if($imgCount === 1)
                    <div class="r-ios-xl overflow-hidden bg-[#f7f7f7]" style="height: 480px;">
                        <button type="button"
                                @click="openGallery('{{ $sectionKeyFor($allImages[0]) }}')"
                                class="block w-full h-full overflow-hidden group">
                            <img src="{{ $allImages[0]->url }}"
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700"
                                 alt=""
                                 loading="eager">
                        </button>
                    </div>

                @elseif($imgCount === 2)
                    <div class="grid grid-cols-2 gap-2 r-ios-xl overflow-hidden" style="height: 480px;">
                        @foreach($allImages as $i => $img)
                            <button type="button"
                                    @click="openGallery('{{ $sectionKeyFor($img) }}')"
                                    class="group relative overflow-hidden bg-[#f7f7f7]">
                                <img src="{{ $img->url }}"
                                     class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700"
                                     alt=""
                                     loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                            </button>
                        @endforeach
                    </div>

                @elseif($imgCount === 3)
                    <div class="r-ios-xl overflow-hidden"
                         style="display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(2, 1fr); gap: 8px; height: 480px;">
                        <button type="button"
                                @click="openGallery('{{ $sectionKeyFor($allImages[0]) }}')"
                                style="grid-column: span 2; grid-row: span 2;"
                                class="group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $allImages[0]->url }}"
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700"
                                 alt=""
                                 loading="eager">
                        </button>
                        <button type="button"
                                @click="openGallery('{{ $sectionKeyFor($allImages[1]) }}')"
                                class="group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $allImages[1]->url }}"
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700"
                                 alt=""
                                 loading="lazy">
                        </button>
                        <button type="button"
                                @click="openGallery('{{ $sectionKeyFor($allImages[2]) }}')"
                                class="group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $allImages[2]->url }}"
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700"
                                 alt=""
                                 loading="lazy">
                        </button>
                    </div>

                @else
                    {{-- 4+ images: 1 big + 3 small + "+N more" button --}}
                    @php $more = $imgCount - 4; @endphp
                    <div class="r-ios-xl overflow-hidden"
                         style="display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr); gap: 8px; height: 480px;">
                        {{-- big --}}
                        <button type="button"
                                @click="openGallery('{{ $sectionKeyFor($allImages[0]) }}')"
                                style="grid-column: span 2; grid-row: span 2;"
                                class="group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $allImages[0]->url }}"
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700"
                                 alt=""
                                 loading="eager">
                        </button>

                        {{-- 3 small image cells --}}
                        @for($i = 1; $i <= 3; $i++)
                            <button type="button"
                                    @click="openGallery('{{ $sectionKeyFor($allImages[$i]) }}')"
                                    class="group relative overflow-hidden bg-[#f7f7f7]">
                                <img src="{{ $allImages[$i]->url }}"
                                     class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700"
                                     alt=""
                                     loading="lazy">
                            </button>
                        @endfor

                        {{-- "more" cell — opens full-page photo tour --}}
                        <button type="button"
                                @click="openGallery('')"
                                class="group relative overflow-hidden flex flex-col items-center justify-center text-white bg-[#222] hover:bg-black transition-colors {{ $fa }}">
                            @if($more > 0)
                                <div class="text-[36px] font-bold leading-none tabular-nums">+{{ $more }}</div>
                                <div class="mt-2 text-[13px] font-semibold">{{ $isRtl ? 'صورة أخرى' : 'more photos' }}</div>
                            @else
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                                <div class="mt-2 text-[13px] font-semibold">{{ $isRtl ? 'عرض الكل' : 'View all' }}</div>
                            @endif
                        </button>
                    </div>
                @endif
            </div>

            {{-- Mobile horizontal scroll carousel (constrained to page width) --}}
            <div class="sm:hidden"
                 x-data="{
                    idx: 0,
                    total: {{ $allImages->count() }},
                    onScroll(el) { const w = el.clientWidth; if (w) this.idx = Math.round(Math.abs(el.scrollLeft) / w); },
                    prev() { const t = this.$refs.heroTrack; if (t) t.scrollBy({ left: -t.clientWidth, behavior: 'smooth' }); },
                    next() { const t = this.$refs.heroTrack; if (t) t.scrollBy({ left:  t.clientWidth, behavior: 'smooth' }); },
                 }">
                <div class="relative overflow-hidden bg-[#f7f7f7]"
                     style="border-radius: 20px; corner-shape: squircle;">
                    <div class="flex no-scrollbar"
                         style="overflow-x: auto; overflow-y: hidden; scroll-snap-type: x mandatory; aspect-ratio: 4 / 3; width: 100%;"
                         x-ref="heroTrack"
                         @scroll.passive="onScroll($event.target)"
                         dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                        @foreach($allImages as $i => $img)
                            <button type="button"
                                    @click="openGallery('{{ $sectionKeyFor($img) }}')"
                                    style="width: 100%; height: 100%; flex-shrink: 0; scroll-snap-align: center;">
                                <img src="{{ $img->url }}"
                                     style="width: 100%; height: 100%; object-fit: cover; pointer-events: none;"
                                     alt=""
                                     draggable="false"
                                     loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                            </button>
                        @endforeach
                    </div>

                    @if($allImages->count() > 1)
                        {{-- counter pill --}}
                        <div class="absolute bottom-3 {{ $isRtl ? 'left-3' : 'right-3' }} text-white text-xs font-bold pointer-events-none"
                             style="background: rgba(0,0,0,0.6); padding: 5px 11px; border-radius: 999px; corner-shape: squircle; backdrop-filter: blur(8px);"
                             dir="ltr">
                            <span x-text="idx + 1"></span> <span class="opacity-60">/</span> {{ $allImages->count() }}
                        </div>

                        {{-- prev / next chevron buttons --}}
                        <button type="button"
                                @click.stop="prev()"
                                aria-label="previous"
                                class="absolute top-1/2 -translate-y-1/2 left-3 flex items-center justify-center text-[#222] bg-white hover:bg-[#f7f7f7] active:scale-95 transition-all"
                                style="width: 36px; height: 36px; border-radius: 999px; corner-shape: squircle; box-shadow: 0 4px 12px rgba(0,0,0,0.18);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <button type="button"
                                @click.stop="next()"
                                aria-label="next"
                                class="absolute top-1/2 -translate-y-1/2 right-3 flex items-center justify-center text-[#222] bg-white hover:bg-[#f7f7f7] active:scale-95 transition-all"
                                style="width: 36px; height: 36px; border-radius: 999px; corner-shape: squircle; box-shadow: 0 4px 12px rgba(0,0,0,0.18);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    @endif
                </div>

                @if($allImages->count() > 1)
                    {{-- dot indicators (carousel position) --}}
                    @if($allImages->count() <= 12)
                        <div class="flex justify-center items-center" style="gap: 6px; margin-top: 14px;" dir="ltr">
                            @foreach($allImages as $i => $img)
                                <span class="block transition-all"
                                      :style="idx === {{ $i }}
                                        ? 'width: 20px; height: 6px; border-radius: 999px; background-color: #222;'
                                        : 'width: 6px; height: 6px; border-radius: 999px; background-color: #9ca3af;'"></span>
                            @endforeach
                        </div>
                    @endif

                    {{-- "View all images" CTA --}}
                    <div class="flex justify-center" style="margin-top: 12px;">
                        <button type="button"
                                @click="openGallery('')"
                                class="inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7] active:scale-[0.98] transition-all {{ $fa }}"
                                style="gap: 6px; padding: 7px 14px; font-size: 13px; border: 1px solid #222; border-radius: 999px; corner-shape: squircle;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            <span>{{ $isRtl ? "عرض كل الصور ({$allImages->count()})" : "View all {$allImages->count()} photos" }}</span>
                        </button>
                    </div>
                @endif
            </div>
        @endif

        {{-- FULL-WIDTH BODY --}}
        <div style="margin-top: 48px;">

                {{-- DESCRIPTION (collapsed by default; full text in modal) --}}
                @if($description)
                    <section class="{{ $start }} border-b border-[#ebebeb]"
                             style="padding-bottom: 56px;">
                        <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 20px;">
                            {{ $isRtl ? 'الوصف' : 'About this place' }}
                        </h2>
                        <p class="text-[16px] text-[#222] {{ $fa }}"
                           style="line-height: 1.7; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 4; line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">{{ $description }}</p>
                        @if(mb_strlen($description) > 200)
                            <button type="button"
                                    @click="openDescription()"
                                    class="w-full flex items-center justify-center font-semibold text-[#222] hover:bg-[#ebebeb] transition-colors {{ $fa }}"
                                    style="margin-top: 20px; padding: 14px 20px; background-color: #f7f7f7; border-radius: 14px; corner-shape: squircle;">
                                <span>{{ $isRtl ? 'عرض المزيد' : 'Show more' }}</span>
                            </button>
                        @endif
                    </section>
                @endif

                {{-- قائمة المرافق — list with × count + expandable description per row --}}
                @if($host->facilities->count() > 0)
                    <section class="border-b border-[#ebebeb]" style="padding-top: 56px; padding-bottom: 56px;">
                        <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}" style="margin-bottom: 24px;">
                            {{ $isRtl ? 'قائمة المرافق' : 'Facilities list' }}
                        </h2>
                        <ul class="{{ $start }} {{ $fa }}" style="list-style: none; padding: 0; margin: 0;">
                            @foreach($host->facilities as $f)
                                <li style="padding: 14px 0; border-bottom: 1px solid #ebebeb;"
                                    x-data="{ expanded: false }">
                                    <div class="flex items-center" style="gap: 12px;">
                                        <span style="width: 6px; height: 6px; background: #F88379; border-radius: 999px; flex-shrink: 0; display: inline-block;"></span>
                                        <span class="text-[15px] sm:text-[16px] text-[#222] flex-1 font-medium">{{ Catalog::facilityLabel($f->key, $locale) }}</span>
                                        <span class="text-[14px] font-bold text-[#222] tabular-nums" dir="ltr">×{{ $f->count }}</span>
                                    </div>
                                    @if($f->description)
                                        <div style="padding-{{ $isRtl ? 'right' : 'left' }}: 18px; margin-top: 8px;">
                                            <p class="text-[14px] text-[#717171]"
                                               :style="expanded
                                                   ? 'line-height: 1.65;'
                                                   : 'line-height: 1.65; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;'">{{ $f->description }}</p>
                                            @if(mb_strlen($f->description) > 100)
                                                <button type="button"
                                                        @click="expanded = !expanded"
                                                        class="text-[13px] font-semibold text-[#222] underline underline-offset-2 hover:text-black"
                                                        style="margin-top: 6px;">
                                                    <span x-show="!expanded">{{ $isRtl ? 'عرض المزيد' : 'Show more' }}</span>
                                                    <span x-show="expanded" x-cloak>{{ $isRtl ? 'عرض أقل' : 'Show less' }}</span>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- FACILITIES IMAGES (carousel — always LTR regardless of page direction) --}}
                @if($host->facilities->count() > 0)
                    <section class="border-b border-[#ebebeb]"
                             dir="ltr"
                             style="padding-top: 56px; padding-bottom: 56px;"
                             x-data="{
                                idx: 0,
                                total: {{ $host->facilities->count() }},
                                init() {
                                    this.$nextTick(() => {
                                        if (this.$refs.track) this.$refs.track.scrollLeft = 0;
                                        this.idx = 0;
                                    });
                                },
                                slides()    { const t = this.$refs.track; if (!t || !t.firstElementChild) return []; return Array.from(t.firstElementChild.children); },
                                slideLeft(el) { const t = this.$refs.track; return el.getBoundingClientRect().left - t.getBoundingClientRect().left + t.scrollLeft; },
                                onScroll()  {
                                    const t = this.$refs.track; if (!t) return;
                                    const slides = this.slides();
                                    if (!slides.length) return;
                                    let best = 0, bestDist = Infinity;
                                    const x = t.scrollLeft;
                                    slides.forEach((s, i) => {
                                        const d = Math.abs(this.slideLeft(s) - x);
                                        if (d < bestDist) { bestDist = d; best = i; }
                                    });
                                    this.idx = best;
                                },
                                go(i) {
                                    const t = this.$refs.track; if (!t) return;
                                    const slides = this.slides();
                                    if (!slides[i]) return;
                                    this.idx = i;
                                    t.scrollTo({ left: this.slideLeft(slides[i]), behavior: 'smooth' });
                                },
                                next()      { this.go((this.idx + 1) % this.total); },
                                prev()      { this.go((this.idx - 1 + this.total) % this.total); },
                             }">
                        <div class="flex items-end justify-between gap-4">
                            <div class="{{ $start }} {{ $fa }}">
                                <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222]">
                                    {{ $isRtl ? 'صور المرافق' : 'Facilities images' }}
                                </h2>
                                <p class="text-[15px] text-[#717171]" style="margin-top: 4px;">
                                    {{ $isRtl ? 'استكشف كل مساحات المكان' : 'Browse every space of the place' }}
                                </p>
                            </div>
                            <div class="flex items-center shrink-0" style="gap: 10px;" dir="ltr">
                                {{-- LEFT chevron = previous --}}
                                <button type="button"
                                        @click="prev()"
                                        aria-label="previous"
                                        class="flex items-center justify-center text-[#222] hover:text-black active:scale-95 transition-all"
                                        style="width: 40px; height: 40px; border-radius: 999px; corner-shape: squircle; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="15 18 9 12 15 6"></polyline>
                                    </svg>
                                </button>
                                {{-- RIGHT chevron = next --}}
                                <button type="button"
                                        @click="next()"
                                        aria-label="next"
                                        class="flex items-center justify-center text-[#222] hover:text-black active:scale-95 transition-all"
                                        style="width: 40px; height: 40px; border-radius: 999px; corner-shape: squircle; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto no-scrollbar"
                             x-ref="track"
                             dir="ltr"
                             @scroll.passive="onScroll"
                             style="margin-top: 28px; scroll-behavior: smooth; scroll-snap-type: x mandatory;">
                            <div class="flex" style="width: max-content; gap: 20px;">
                                @foreach($host->facilities as $f)
                                    @php $firstImg = $f->images->first(); @endphp
                                    <div class="shrink-0 {{ $start }}"
                                         style="width: 180px; scroll-snap-align: start;"
                                         x-data="{ expanded: false }">
                                        <button type="button"
                                                @click="{{ $firstImg ? "openGallery('{$f->key}')" : '' }}"
                                                class="block group {{ $firstImg ? 'cursor-zoom-in' : 'cursor-default' }}"
                                                style="width: 180px;"
                                                @if(!$firstImg) disabled @endif>
                                            @if($firstImg)
                                                <div class="overflow-hidden bg-[#f7f7f7]"
                                                     style="width: 180px; height: 180px; border-radius: 20px; corner-shape: squircle;">
                                                    <img src="{{ $firstImg->url }}"
                                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                         alt=""
                                                         loading="lazy">
                                                </div>
                                            @else
                                                <div class="flex items-center justify-center text-4xl text-[#cecece] bg-[#f7f7f7]"
                                                     style="width: 180px; height: 180px; border-radius: 20px; corner-shape: squircle;">🏠</div>
                                            @endif
                                            <div class="flex items-center gap-2 {{ $fa }}" style="margin-top: 12px;">
                                                <h3 class="text-[15px] font-bold text-[#222]">
                                                    {{ Catalog::facilityLabel($f->key, $locale) }}
                                                </h3>
                                                <span class="text-xs font-bold text-[#222] bg-[#f7f7f7] tabular-nums"
                                                      style="padding: 3px 9px; border-radius: 999px; corner-shape: squircle;">
                                                    {{ $f->count }}
                                                </span>
                                            </div>
                                        </button>
                                        @if($f->description)
                                            <div class="{{ $fa }}" style="margin-top: 4px;">
                                                <p class="text-[13px] text-[#717171]"
                                                   :style="expanded
                                                       ? 'line-height: 1.55;'
                                                       : 'line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;'">{{ $f->description }}</p>
                                                @if(mb_strlen($f->description) > 90)
                                                    <button type="button"
                                                            @click.stop="expanded = !expanded"
                                                            class="text-[12px] font-semibold text-[#222] underline underline-offset-2"
                                                            style="margin-top: 4px;">
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

                        {{-- Dot indicators --}}
                        @if($host->facilities->count() > 1 && $host->facilities->count() <= 20)
                            <div class="flex justify-center items-center" style="gap: 6px; margin-top: 20px;" dir="ltr">
                                @foreach($host->facilities as $i => $f)
                                    <button type="button"
                                            @click="go({{ $i }})"
                                            aria-label="go to slide {{ $i + 1 }}"
                                            class="block transition-all"
                                            :style="idx === {{ $i }}
                                                ? 'width: 24px; height: 6px; background: #222; border-radius: 999px;'
                                                : 'width: 6px; height: 6px; background: #cbd5e1; border-radius: 999px;'"></button>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                {{-- AMENITIES --}}
                @if($host->amenities->count() > 0)
                    <section class="border-b border-[#ebebeb]" style="padding-top: 56px; padding-bottom: 56px;">
                        <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] mb-1 {{ $start }} {{ $fa }}">
                            {{ $isRtl ? 'ما يقدمه هذا المكان' : 'What this place offers' }}
                        </h2>
                        <p class="text-[15px] text-[#717171] mb-6 {{ $start }} {{ $fa }}">
                            {{ $host->amenities->count() }} {{ $isRtl ? 'ميزة متوفرة' : 'amenities available' }}
                        </p>

                        <div class="flex flex-wrap" style="gap: 12px; margin-top: 24px;">
                            @foreach($previewAmenities as $a)
                                <div class="inline-flex items-center bg-white hover:-translate-y-0.5 transition-all duration-200"
                                     style="gap: 10px; padding: 14px 18px; border-radius: 16px; corner-shape: squircle; box-shadow: 0 6px 18px rgba(0,0,0,0.06);">
                                    <span class="leading-none shrink-0" style="font-size: 22px;">{{ Catalog::amenityEmoji($a->key) ?: '·' }}</span>
                                    <span class="text-[15px] font-medium text-[#222] whitespace-nowrap {{ $fa }}">{{ Catalog::amenityLabel($a->key, $locale) }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if($host->amenities->count() > 10)
                            <button type="button"
                                    @click="openSheet('amenities')"
                                    class="mt-6 inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                                    style="padding: 12px 22px; border: 1px solid #222; border-radius: 12px; corner-shape: squircle;">
                                {{ $isRtl ? "عرض جميع المميزات ({$host->amenities->count()})" : "Show all {$host->amenities->count()} amenities" }}
                            </button>
                        @endif
                    </section>
                @endif

                {{-- LOCATION (last section — no bottom border) --}}
                @if($host->maps_url || $host->latitude && $host->longitude)
                    <section style="padding-top: 56px; padding-bottom: 56px;">
                        <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}" style="margin-bottom: 8px;">
                            {{ __('location_section') }}
                        </h2>
                        @if($host->address)
                            <p class="text-[15px] text-[#717171] {{ $start }} {{ $fa }}" style="margin-bottom: 24px;">
                                {{ $host->address }}
                            </p>
                        @else
                            <div style="margin-bottom: 24px;"></div>
                        @endif

                        @php
                            $embedSrc = null;
                            // 1. If the host pasted the official "Embed a map" URL, use it directly (best case).
                            if ($host->maps_url && str_contains($host->maps_url, '/maps/embed?pb=')) {
                                $embedSrc = $host->maps_url;
                            }
                            // 2. Else if we have coordinates, use OpenStreetMap — reliable, no API key, no iframe-blocking.
                            elseif ($host->latitude && $host->longitude) {
                                $lat  = (float) $host->latitude;
                                $lng  = (float) $host->longitude;
                                $d    = 0.005;
                                $bbox = implode(',', [$lng - $d, $lat - $d, $lng + $d, $lat + $d]);
                                $embedSrc = "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker={$lat},{$lng}";
                            }
                        @endphp

                        @if($embedSrc)
                            <div class="overflow-hidden bg-[#f7f7f7]"
                                 style="border-radius: 24px; corner-shape: squircle; box-shadow: 0 6px 18px rgba(0,0,0,0.06);">
                                <iframe
                                    src="{{ $embedSrc }}"
                                    width="100%"
                                    class="map-embed-frame"
                                    style="border: 0; display: block;"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    allowfullscreen></iframe>
                            </div>
                        @endif

                        <a href="{{ $host->maps_url ?? ('https://www.google.com/maps/?q=' . $host->latitude . ',' . $host->longitude) }}"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center font-bold text-white bg-[#F88379] hover:bg-[#f56b60] active:scale-[0.98] transition-all {{ $fa }}"
                           style="margin-top: 24px; padding: 12px 22px; gap: 8px; border-radius: 16px; corner-shape: squircle; box-shadow: 0 6px 12px rgba(248,131,121,0.25);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span>{{ __('open_in_maps') }}</span>
                        </a>
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

    {{-- ─────────── DESCRIPTION MODAL (full white, like gallery) ─────────── --}}
    <div x-show="description" x-cloak
         x-transition.opacity
         id="description-scroll"
         style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 60; background-color: #ffffff; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch;"
         dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

        {{-- Sticky top header — close + title, blurry like the page header --}}
        <div class="sticky top-0 border-b border-[#ebebeb]"
             style="z-index: 2; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
            <div class="max-w-3xl mx-auto flex items-center justify-between px-6 sm:px-10 h-20">
                <button type="button"
                        @click="closeDescription()"
                        class="w-10 h-10 flex items-center justify-center text-[#222] hover:bg-[#f7f7f7] transition-colors"
                        style="border-radius: 999px; corner-shape: squircle;"
                        aria-label="close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>

                <h2 class="text-[16px] sm:text-[18px] font-bold text-[#222] {{ $fa }}">
                    {{ $isRtl ? 'الوصف' : 'About this place' }}
                </h2>

                {{-- spacer to balance the layout --}}
                <div class="w-10 h-10"></div>
            </div>
        </div>

        {{-- Content --}}
        <div class="max-w-3xl mx-auto px-6 sm:px-10 {{ $start }}" style="padding-top: 32px; padding-bottom: 160px;">
            <p class="text-[16px] sm:text-[17px] text-[#222] {{ $fa }}" style="line-height: 1.8; white-space: pre-line;">{{ $description }}</p>
        </div>
    </div>

    {{-- ─────────── AMENITIES SHEET ─────────── --}}
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
                <button type="button"
                        @click="closeSheet()"
                        class="absolute top-5 {{ $isRtl ? 'right-5' : 'left-5' }} w-9 h-9 flex items-center justify-center hover:bg-[#f7f7f7] rounded-full transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
                <div class="text-center font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'كل ما يقدمه هذا المكان' : 'What this place offers' }}</div>
            </div>

            <div class="overflow-y-auto p-6 sm:p-8 {{ $start }}">
                @foreach($groupedAmenities as $group)
                    <div class="{{ !$loop->first ? 'mt-8 pt-8 border-t border-[#ebebeb]' : '' }}">
                        <h3 class="text-[14px] font-bold text-[#717171] uppercase tracking-wide mb-4 {{ $fa }}">{{ $group['label'] }}</h3>
                        @foreach($group['items'] as $item)
                            <div class="flex items-center gap-4 py-3 border-b border-[#ebebeb] last:border-b-0">
                                <span class="text-[22px] leading-none w-8 text-center shrink-0">{{ $item['emoji'] ?? '·' }}</span>
                                <span class="text-[16px] text-[#222] {{ $fa }}">{{ $item[$locale] ?? $item['en'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ─────────── PHOTO TOUR GALLERY (full white modal) ─────────── --}}
    <div x-show="gallery" x-cloak
         x-transition.opacity
         id="gallery-scroll"
         style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 60; background-color: #ffffff; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch;"
         dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

        {{-- Sticky top header — close only, blurry like the page header --}}
        <div class="sticky top-0 border-b border-[#ebebeb]"
             style="z-index: 2; background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
            <div class="max-w-7xl mx-auto flex items-center justify-between px-6 sm:px-10 lg:px-20 h-20">
                <button type="button"
                        @click="closeGallery()"
                        class="w-10 h-10 flex items-center justify-center text-[#222] hover:bg-[#f7f7f7] transition-colors"
                        style="border-radius: 999px; corner-shape: squircle;"
                        aria-label="close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>

                <h2 class="text-[16px] sm:text-[18px] font-bold text-[#222] {{ $fa }}">
                    {{ $isRtl ? 'جولة بالصور' : 'Photo tour' }}
                </h2>

                {{-- spacer to balance the layout --}}
                <div class="w-10 h-10"></div>
            </div>
        </div>

        {{-- Content sections — each facility's images at original aspect ratio --}}
        <div class="max-w-7xl mx-auto px-6 sm:px-10 lg:px-20" style="padding-bottom: 160px;">

            {{-- horizontal thumbnails strip (moved into the page body) --}}
            <div class="overflow-x-auto no-scrollbar pt-8 pb-4">
                <div class="flex gap-4 justify-start" style="width: max-content;">
                    @foreach($host->facilities as $f)
                        @if($f->images->count() > 0)
                            <button type="button"
                                    @click="scrollToGallerySection('{{ $f->key }}')"
                                    class="shrink-0 flex flex-col items-center"
                                    style="width: 104px;">
                                <img src="{{ $f->images->first()->url }}"
                                     class="block object-cover hover:opacity-90 transition-opacity"
                                     style="width: 92px; height: 92px; border-radius: 20px; corner-shape: squircle;"
                                     alt=""
                                     loading="lazy">
                                <div class="mt-2 text-[13px] font-semibold text-[#222] text-center {{ $fa }} truncate w-full">
                                    {{ Catalog::facilityLabel($f->key, $locale) }}
                                </div>
                            </button>
                        @endif
                    @endforeach

                    @if($extraImages->count() > 0)
                        <button type="button"
                                @click="scrollToGallerySection('extras')"
                                class="shrink-0 flex flex-col items-center"
                                style="width: 104px;">
                            <img src="{{ $extraImages->first()->url }}"
                                 class="block object-cover hover:opacity-90 transition-opacity"
                                 style="width: 92px; height: 92px; border-radius: 20px; corner-shape: squircle;"
                                 alt=""
                                 loading="lazy">
                            <div class="mt-2 text-[13px] font-semibold text-[#222] text-center {{ $fa }} truncate w-full">
                                {{ $isRtl ? 'صور أخرى' : 'More' }}
                            </div>
                        </button>
                    @endif
                </div>
            </div>
            @foreach($host->facilities as $f)
                @if($f->images->count() > 0)
                    <section id="gallery-section-{{ $f->key }}"
                             style="padding-top: 32px; scroll-margin-top: 100px;">
                        <h3 class="text-[22px] sm:text-[26px] font-bold text-[#222] {{ $fa }}" style="line-height: 1.2;">
                            {{ Catalog::facilityLabel($f->key, $locale) }}
                        </h3>
                        @if($f->description)
                            <p class="mt-2 text-[14px] sm:text-[15px] text-[#6B7280] {{ $fa }}" style="line-height: 1.6;">
                                {{ $f->description }}
                            </p>
                        @endif
                        <div class="mt-6 space-y-5">
                            @foreach($f->images as $img)
                                <img src="{{ $img->url }}"
                                     class="w-full block bg-[#f7f7f7]"
                                     style="border-radius: 40px; corner-shape: squircle;"
                                     alt=""
                                     loading="lazy">
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if($extraImages->count() > 0)
                <section id="gallery-section-extras"
                         style="padding-top: 32px; scroll-margin-top: 100px;">
                    <h3 class="text-[22px] sm:text-[26px] font-bold text-[#222] {{ $fa }}" style="line-height: 1.2;">
                        {{ $isRtl ? 'صور أخرى' : 'More photos' }}
                    </h3>
                    <p class="mt-2 text-[14px] text-[#6B7280] {{ $fa }}">
                        {{ $extraImages->count() }} {{ $isRtl ? 'صورة' : ($extraImages->count() === 1 ? 'photo' : 'photos') }}
                    </p>
                    <div class="mt-5 space-y-4">
                        @foreach($extraImages as $img)
                            <img src="{{ $img->url }}"
                                 class="w-full block bg-[#f7f7f7]"
                                 style="border-radius: 20px; corner-shape: squircle;"
                                 alt=""
                                 loading="lazy">
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
    .map-embed-frame { height: 320px; }
    @media (min-width: 640px) {
        .map-embed-frame { height: 420px; }
    }
</style>
@endsection
