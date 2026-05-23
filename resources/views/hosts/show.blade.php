@extends('layouts.app')

@section('title', 'Calm — ' . $host->slug)

@section('body')
@php
    use App\Support\Catalog;

    $locale = app()->getLocale();
    $isRtl  = $locale === 'ar';
    $fa     = $isRtl ? 'font-arabic' : '';
    $start  = $isRtl ? 'text-right' : 'text-left';

    $allImages   = $host->images->values();
    $extraImages = $host->images->whereNull('host_facility_id')->values();
    $placeLabel  = Catalog::placeTypeLabel($host->place_type, $locale);

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
    dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
    x-data="{
        lightbox: false,
        lightboxIdx: 0,
        lightboxImages: @js($allImages->pluck('url')->all()),
        sheet: null,
        open(idx) { this.lightboxIdx = idx; this.lightbox = true; document.body.style.overflow = 'hidden'; },
        close() { this.lightbox = false; if (!this.sheet) document.body.style.overflow = ''; },
        next() { this.lightboxIdx = (this.lightboxIdx + 1) % this.lightboxImages.length; },
        prev() { this.lightboxIdx = (this.lightboxIdx - 1 + this.lightboxImages.length) % this.lightboxImages.length; },
        openSheet(name) { this.sheet = name; document.body.style.overflow = 'hidden'; },
        closeSheet() { this.sheet = null; if (!this.lightbox) document.body.style.overflow = ''; },
    }"
    @keydown.escape.window="close(); closeSheet();"
    @keydown.arrow-right.window="if (lightbox) { {{ $isRtl ? 'prev()' : 'next()' }} }"
    @keydown.arrow-left.window="if (lightbox) { {{ $isRtl ? 'next()' : 'prev()' }} }"
>

    {{-- HEADER --}}
    <header class="w-full border-b border-[#ebebeb] sticky top-0 z-30"
            style="background-color: rgba(255,255,255,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
            </a>
            <form method="POST" action="{{ url('/locale/' . ($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                @csrf
                <button type="submit"
                        class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] px-4 py-3 transition-colors">
                    {{ $locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-7xl mx-auto w-full px-6 sm:px-10 lg:px-20 py-8 sm:py-10">

        {{-- TITLE BLOCK --}}
        <div class="{{ $start }} mb-6">
            <h1 class="text-[26px] sm:text-[32px] font-bold tracking-tight text-[#222] {{ $fa }}" style="line-height: 1.2;">
                {{ $host->title ?? $placeLabel }}
            </h1>
            <div class="mt-2 text-[15px] text-[#717171] {{ $fa }}">
                <span>{{ $placeLabel }}</span>
                @if($host->max_guests)
                    <span class="mx-2 text-[#cecece]">·</span>
                    <span>{{ $host->max_guests }} {{ $isRtl ? 'ضيف' : 'guests' }}</span>
                @endif
                @foreach($host->facilities->take(4) as $f)
                    <span class="mx-2 text-[#cecece]">·</span>
                    <span>{{ $f->count }} {{ Catalog::facilityLabel($f->key, $locale) }}</span>
                @endforeach
            </div>
        </div>

        {{-- AIRBNB-STYLE MOSAIC (desktop) / SCROLL CAROUSEL (mobile) --}}
        @if($allImages->count() > 0)
            @php $imgCount = $allImages->count(); @endphp
            {{-- Desktop mosaic — layout adapts to image count --}}
            <div class="hidden sm:block relative">
                @php
                    // grid template per count
                    $gridClass = match (true) {
                        $imgCount === 1 => 'grid grid-cols-1',
                        $imgCount === 2 => 'grid grid-cols-2 grid-rows-1',
                        $imgCount === 3 => 'grid grid-cols-3 grid-rows-2',
                        $imgCount === 4 => 'grid grid-cols-2 grid-rows-2',
                        default         => 'grid grid-cols-4 grid-rows-2',
                    };
                    // cell span per index for this count
                    $cellSpan = function (int $i) use ($imgCount): string {
                        return match (true) {
                            $imgCount === 1                       => 'col-span-1 row-span-1',
                            $imgCount === 2                       => 'col-span-1 row-span-1',
                            $imgCount === 3 && $i === 0           => 'col-span-2 row-span-2',
                            $imgCount === 3                       => 'col-span-1 row-span-1',
                            $imgCount === 4                       => 'col-span-1 row-span-1',
                            $i === 0                              => 'col-span-2 row-span-2',
                            default                               => 'col-span-1 row-span-1',
                        };
                    };
                    $renderCount = min($imgCount, 5);
                @endphp

                <div class="{{ $gridClass }} gap-2 r-ios-xl overflow-hidden" style="height: 480px;">
                    @for($i = 0; $i < $renderCount; $i++)
                        <button type="button"
                                @click="open({{ $i }})"
                                class="{{ $cellSpan($i) }} group relative overflow-hidden bg-[#f7f7f7]">
                            <img src="{{ $allImages[$i]->url }}"
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-700"
                                 alt=""
                                 loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                        </button>
                    @endfor
                </div>

                {{-- "Show all photos" button overlay (only when there are more than rendered) --}}
                @if($imgCount > $renderCount)
                    <button type="button"
                            @click="open(0)"
                            class="absolute bottom-4 {{ $isRtl ? 'left-4' : 'right-4' }} bg-white text-[#222] text-sm font-semibold flex items-center gap-2 hover:bg-[#f7f7f7] transition-colors"
                            style="padding: 10px 16px; border-radius: 12px; corner-shape: squircle; border: 1px solid #222;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span class="{{ $fa }}">{{ $isRtl ? "عرض جميع الصور ({$imgCount})" : "Show all {$imgCount} photos" }}</span>
                    </button>
                @endif
            </div>

            {{-- Mobile horizontal scroll carousel --}}
            <div class="sm:hidden -mx-6"
                 x-data="{
                    idx: 0,
                    total: {{ $allImages->count() }},
                    onScroll(el) { const w = el.clientWidth; if (w) this.idx = Math.round(Math.abs(el.scrollLeft) / w); },
                 }">
                <div class="relative">
                    <div class="flex overflow-x-auto snap-x snap-mandatory no-scrollbar bg-[#f7f7f7]"
                         style="aspect-ratio: 4 / 3;"
                         @scroll.passive="onScroll($event.target)"
                         dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                        @foreach($allImages as $i => $img)
                            <button type="button" @click="open({{ $i }})" class="snap-center shrink-0 w-full h-full">
                                <img src="{{ $img->url }}"
                                     class="w-full h-full object-cover pointer-events-none"
                                     alt=""
                                     draggable="false"
                                     loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                            </button>
                        @endforeach
                    </div>
                    @if($allImages->count() > 1)
                        <div class="absolute bottom-3 {{ $isRtl ? 'left-3' : 'right-3' }} text-white text-xs font-bold pointer-events-none"
                             style="background: rgba(0,0,0,0.6); padding: 5px 11px; border-radius: 999px; corner-shape: squircle; backdrop-filter: blur(8px);"
                             dir="ltr">
                            <span x-text="idx + 1"></span> <span class="opacity-60">/</span> {{ $allImages->count() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- TWO-COLUMN BODY --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-16 mt-12">

            {{-- MAIN COLUMN --}}
            <div class="lg:col-span-2">

                {{-- DESCRIPTION --}}
                @if($description)
                    <section class="{{ $start }} pb-10 border-b border-[#ebebeb]">
                        <h2 class="text-2xl font-semibold text-[#222] mb-4 {{ $fa }}">
                            {{ $isRtl ? 'الوصف' : 'About this place' }}
                        </h2>
                        <p class="text-[16px] text-[#222] {{ $fa }}"
                           style="line-height: 1.7; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 6; line-clamp: 6; -webkit-box-orient: vertical; overflow: hidden;">{{ $description }}</p>
                        @if(mb_strlen($description) > 280)
                            <button type="button"
                                    @click="openSheet('description')"
                                    class="mt-5 inline-flex items-center gap-1.5 font-semibold text-[#222] hover:text-black underline underline-offset-4 decoration-2 {{ $fa }}">
                                {{ $isRtl ? 'عرض المزيد' : 'Show more' }}
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="{{ $isRtl ? 'transform: scaleX(-1);' : '' }}"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </button>
                        @endif
                    </section>
                @endif

                {{-- THE SPACES --}}
                @if($host->facilities->count() > 0)
                    <section class="pt-10 pb-10 border-b border-[#ebebeb]">
                        <h2 class="text-2xl font-semibold text-[#222] mb-1 {{ $start }} {{ $fa }}">
                            {{ $isRtl ? 'المرافق' : 'The spaces' }}
                        </h2>
                        <p class="text-[15px] text-[#717171] mb-6 {{ $start }} {{ $fa }}">
                            {{ $isRtl ? 'استكشف كل مساحات المكان' : 'Browse every space of the place' }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            @foreach($host->facilities as $f)
                                @php
                                    $firstImg = $f->images->first();
                                    $globalIdx = $firstImg ? $allImages->search(fn($i) => $i->id === $firstImg->id) : null;
                                @endphp
                                <button type="button"
                                        @click="{{ $globalIdx !== null && $globalIdx !== false ? "open({$globalIdx})" : '' }}"
                                        class="block text-{{ $isRtl ? 'right' : 'left' }} bg-white border border-[#ebebeb] shadow-card hover:shadow-card-hover transition-shadow overflow-hidden r-ios-lg {{ $firstImg ? 'cursor-zoom-in' : 'cursor-default' }}"
                                        @if(!$firstImg) disabled @endif>
                                    @if($firstImg)
                                        <div class="aspect-[16/10] overflow-hidden bg-[#f7f7f7]">
                                            <img src="{{ $firstImg->url }}"
                                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                                                 alt=""
                                                 loading="lazy">
                                        </div>
                                    @else
                                        <div class="aspect-[16/10] bg-[#f7f7f7] flex items-center justify-center text-4xl text-[#cecece]">🏠</div>
                                    @endif
                                    <div class="p-5">
                                        <div class="flex items-center gap-2 {{ $fa }}">
                                            <h3 class="text-[17px] font-bold text-[#222]">
                                                {{ Catalog::facilityLabel($f->key, $locale) }}
                                            </h3>
                                            <span class="text-xs font-bold text-[#222] bg-[#f7f7f7] tabular-nums"
                                                  style="padding: 3px 10px; border-radius: 999px; corner-shape: squircle;">
                                                {{ $f->count }}
                                            </span>
                                        </div>
                                        @if($f->images->count() > 0)
                                            <div class="mt-1 text-[13px] text-[#717171] {{ $fa }}">
                                                {{ $f->images->count() }} {{ $isRtl ? 'صور' : 'photos' }}
                                            </div>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- AMENITIES --}}
                @if($host->amenities->count() > 0)
                    <section class="pt-10 pb-10 border-b border-[#ebebeb]">
                        <h2 class="text-2xl font-semibold text-[#222] mb-1 {{ $start }} {{ $fa }}">
                            {{ $isRtl ? 'ما يقدمه هذا المكان' : 'What this place offers' }}
                        </h2>
                        <p class="text-[15px] text-[#717171] mb-6 {{ $start }} {{ $fa }}">
                            {{ $host->amenities->count() }} {{ $isRtl ? 'ميزة متوفرة' : 'amenities available' }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                            @foreach($previewAmenities as $a)
                                <div class="flex items-center gap-4 py-3 border-b border-[#ebebeb] last:border-b-0">
                                    <span class="text-[22px] leading-none w-8 text-center shrink-0">{{ Catalog::amenityEmoji($a->key) ?: '·' }}</span>
                                    <span class="text-[16px] text-[#222] {{ $fa }}">{{ Catalog::amenityLabel($a->key, $locale) }}</span>
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

                {{-- HOUSE RULES --}}
                <section class="pt-10 pb-10 {{ $extraImages->count() > 0 ? 'border-b border-[#ebebeb]' : '' }}">
                    <h2 class="text-2xl font-semibold text-[#222] mb-6 {{ $start }} {{ $fa }}">
                        {{ $isRtl ? 'قوانين البيت' : 'House rules' }}
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                        @foreach($rules as $rule)
                            <div class="flex items-center gap-4 py-3 border-b border-[#ebebeb] last:border-b-0">
                                <span class="text-[22px] leading-none w-8 text-center shrink-0">{{ $rule['emoji'] }}</span>
                                <span class="text-[16px] text-[#222] {{ $fa }}">{{ $rule['text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- EXTRA PHOTOS --}}
                @if($extraImages->count() > 0)
                    <section class="pt-10">
                        <h2 class="text-2xl font-semibold text-[#222] mb-6 {{ $start }} {{ $fa }}">
                            {{ __('photos') }}
                        </h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($extraImages as $img)
                                @php $idx = $allImages->search(fn($i) => $i->id === $img->id); @endphp
                                <button type="button"
                                        @click="open({{ $idx !== false ? $idx : 0 }})"
                                        class="block w-full overflow-hidden cursor-zoom-in r-ios-lg group">
                                    <img src="{{ $img->url }}"
                                         class="w-full aspect-square object-cover group-hover:scale-105 transition-transform duration-500"
                                         alt=""
                                         loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- STICKY SIDEBAR --}}
            <aside class="lg:sticky lg:top-28 lg:self-start lg:h-fit">
                <div class="bg-white border border-[#ebebeb] shadow-card r-ios-xl p-7 {{ $fa }}">
                    <div class="flex items-center gap-2 mb-5">
                        <span class="text-2xl">🏠</span>
                        <h3 class="text-[18px] font-bold text-[#222]">{{ $isRtl ? 'معلومات المكان' : 'Property info' }}</h3>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-4 border-b border-[#ebebeb]">
                            <span class="text-[14px] text-[#717171]">{{ $isRtl ? 'النوع' : 'Type' }}</span>
                            <span class="text-[14px] font-bold text-[#222]">{{ $placeLabel }}</span>
                        </div>
                        @if($host->max_guests)
                            <div class="flex items-center justify-between pb-4 border-b border-[#ebebeb]">
                                <span class="text-[14px] text-[#717171]">{{ $isRtl ? 'الحد الأقصى للضيوف' : 'Max guests' }}</span>
                                <span class="text-[14px] font-bold text-[#222] tabular-nums">{{ $host->max_guests }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between pb-4 border-b border-[#ebebeb]">
                            <span class="text-[14px] text-[#717171]">{{ $isRtl ? 'إجمالي المساحات' : 'Total spaces' }}</span>
                            <span class="text-[14px] font-bold text-[#222] tabular-nums">{{ $host->facilities->sum('count') }}</span>
                        </div>
                        <div class="flex items-center justify-between pb-4 border-b border-[#ebebeb]">
                            <span class="text-[14px] text-[#717171]">{{ $isRtl ? 'عدد المميزات' : 'Amenities' }}</span>
                            <span class="text-[14px] font-bold text-[#222] tabular-nums">{{ $host->amenities->count() }}</span>
                        </div>
                        <div class="flex items-center justify-between pb-4 border-b border-[#ebebeb]">
                            <span class="text-[14px] text-[#717171]">{{ $isRtl ? 'عدد الصور' : 'Photos' }}</span>
                            <span class="text-[14px] font-bold text-[#222] tabular-nums">{{ $totalImages }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[14px] text-[#717171]">{{ $isRtl ? 'مُدرج منذ' : 'Listed' }}</span>
                            <span class="text-[14px] font-bold text-[#222]">{{ $createdLabel }}</span>
                        </div>
                    </div>

                    <button type="button"
                            onclick="if (navigator.share) { navigator.share({ title: document.title, url: window.location.href }); } else { navigator.clipboard.writeText(window.location.href); this.querySelector('.lbl-default').style.display='none'; this.querySelector('.lbl-copied').style.display='inline-flex'; setTimeout(()=>{this.querySelector('.lbl-default').style.display='inline-flex'; this.querySelector('.lbl-copied').style.display='none';}, 1500); }"
                            class="mt-6 w-full inline-flex items-center justify-center gap-2 font-bold text-white bg-[#F88379] hover:bg-[#f56b60] active:scale-[0.98] transition-all"
                            style="padding: 14px 22px; border-radius: 16px; corner-shape: squircle; box-shadow: 0 6px 12px rgba(248,131,121,0.25);">
                        <span class="lbl-default inline-flex items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                                <polyline points="16 6 12 2 8 6"></polyline>
                                <line x1="12" y1="2" x2="12" y2="15"></line>
                            </svg>
                            <span>{{ $isRtl ? 'مشاركة الرابط' : 'Share link' }}</span>
                        </span>
                        <span class="lbl-copied" style="display: none;">{{ $isRtl ? 'تم النسخ ✓' : 'Copied ✓' }}</span>
                    </button>

                    <div class="mt-5 text-center">
                        <div class="text-[10px] text-[#cecece] font-mono" dir="ltr">#{{ $host->slug }}</div>
                    </div>
                </div>
            </aside>
        </div>

        {{-- FOOTER --}}
        <div class="mt-16 pt-10 border-t border-[#ebebeb] text-center">
            <img src="/favicon.png" alt="Calm" class="h-10 w-auto mx-auto opacity-70">
            <div class="mt-3 text-[13px] text-[#717171] {{ $fa }}">
                {{ $isRtl ? 'مدعوم من' : 'Powered by' }} <span class="font-bold text-[#222]">Calm</span>
            </div>
        </div>
    </main>

    {{-- ─────────── DESCRIPTION SHEET ─────────── --}}
    <div x-show="sheet === 'description'" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/50" @click="closeSheet()" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:max-w-2xl sm:max-h-[85vh] sm:h-fit bg-white flex flex-col"
             style="border-radius: 28px 28px 0 0; corner-shape: squircle;"
             x-show="sheet === 'description'"
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
                <div class="text-center font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'الوصف' : 'About this place' }}</div>
            </div>
            <div class="overflow-y-auto p-6 sm:p-8 {{ $start }}">
                <p class="text-[16px] text-[#222] {{ $fa }}" style="line-height: 1.8; white-space: pre-line;">{{ $description }}</p>
            </div>
        </div>
    </div>

    {{-- ─────────── AMENITIES SHEET ─────────── --}}
    <div x-show="sheet === 'amenities'" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/50" @click="closeSheet()" x-transition.opacity></div>
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

    {{-- ─────────── LIGHTBOX GALLERY ─────────── --}}
    <div x-show="lightbox" x-cloak
         x-transition.opacity
         class="fixed inset-0 z-50 bg-black flex flex-col"
         @click.self="close()">

        {{-- top bar --}}
        <div class="flex items-center justify-between px-6 py-4 shrink-0">
            <button type="button"
                    @click="close()"
                    class="w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 transition-colors"
                    style="border-radius: 999px; corner-shape: squircle;"
                    aria-label="close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <div class="text-white text-sm font-semibold tabular-nums" dir="ltr">
                <span x-text="lightboxIdx + 1"></span> / <span x-text="lightboxImages.length"></span>
            </div>
            <div class="w-10"></div>
        </div>

        {{-- image with prev/next --}}
        <div class="flex-1 flex items-center justify-center relative px-4 sm:px-16 pb-4">
            <button type="button"
                    @click.stop="{{ $isRtl ? 'next()' : 'prev()' }}"
                    class="absolute {{ $isRtl ? 'right-4' : 'left-4' }} top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors z-10"
                    style="border-radius: 999px; corner-shape: squircle;"
                    aria-label="prev">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <img :src="lightboxImages[lightboxIdx]"
                 class="max-w-full max-h-full object-contain select-none"
                 style="border-radius: 12px; corner-shape: squircle;"
                 alt=""
                 @click.stop>
            <button type="button"
                    @click.stop="{{ $isRtl ? 'prev()' : 'next()' }}"
                    class="absolute {{ $isRtl ? 'left-4' : 'right-4' }} top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors z-10"
                    style="border-radius: 999px; corner-shape: squircle;"
                    aria-label="next">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
        </div>
    </div>

</div>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    [x-cloak] { display: none !important; }
</style>
@endsection
