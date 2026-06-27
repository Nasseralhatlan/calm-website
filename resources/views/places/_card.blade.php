@php
    use Illuminate\Support\Facades\Storage;
    $locale = app()->getLocale();
    $isRtl  = $locale === 'ar';
    $fa     = $isRtl ? 'font-arabic' : '';
    $start  = $isRtl ? 'text-right' : 'text-left';

    // Adapt the new schema into the names master used.
    $allImages = $place->photos->values();
    $imgUrl = fn ($p) => str_starts_with($p->path, 'http')
        ? $p->path
        : Storage::disk('s3')->url($p->path);

    $city = $place->cityArea?->city;
    $placeLabel = $isRtl ? $place->type?->name_ar : $place->type?->name_en;

    // Attributes grouped by AttributeGroup (Accommodation / Outdoor / Activities / Privacy)
    // — exactly the grouping that master built via Catalog::amenityGroups().
    $attributesByGroup = $place->attributeValues
        ->filter(fn ($pa) => $pa->attribute)
        ->groupBy(fn ($pa) => $pa->attribute->group?->id);

    // Build a map: catalog attribute_id → host's PlaceAttribute row (for the
    // per-photo description lookup below).
    $paByAttrId = $place->attributeValues->keyBy('attribute_id');

    // Per-day price grid (only shown when at least one day was customized).
    $dayPrices = [
        'sunday' => $place->price_sunday, 'monday' => $place->price_monday, 'tuesday' => $place->price_tuesday,
        'wednesday' => $place->price_wednesday, 'thursday' => $place->price_thursday,
        'friday' => $place->price_friday, 'saturday' => $place->price_saturday,
    ];
    $hasPerDay = collect($dayPrices)->filter(fn ($p) => $p > 0 && $p !== (int) $place->price)->isNotEmpty();
    $dayLabels = $isRtl
        ? ['sunday' => 'الأحد', 'monday' => 'الإثنين', 'tuesday' => 'الثلاثاء', 'wednesday' => 'الأربعاء', 'thursday' => 'الخميس', 'friday' => 'الجمعة', 'saturday' => 'السبت']
        : ['sunday' => 'Sun', 'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat'];
@endphp

{{-- ── TITLE BLOCK (centered, like master) ── --}}
<div class="text-center" style="margin-bottom: 24px;">
    <h1 class="text-[26px] sm:text-[32px] font-bold tracking-tight text-[#222] {{ $fa }}" style="line-height: 1.2;">
        {{ $place->localized_title ?: ($isRtl ? '— بدون عنوان —' : '— Untitled —') }}
    </h1>
    <div class="flex flex-wrap items-center justify-center {{ $fa }}" style="gap: 8px; margin-top: 12px;">
        {{-- Place type pill with the type icon --}}
        <span class="inline-flex items-center text-[13px] text-[#717171] bg-[#fafafa]" style="gap: 6px; padding: 4px 12px; border-radius: 999px;">
            <span>{{ $place->type?->icon ?: '🏠' }}</span>
            <span>{{ $placeLabel }}</span>
        </span>
        {{-- City + area pill --}}
        @if($city)
            <span class="inline-flex items-center text-[13px] text-[#717171] bg-[#fafafa]" style="gap: 6px; padding: 4px 12px; border-radius: 999px;">
                <span>{{ $city->avatar ?: '📍' }}</span>
                <span>{{ $isRtl ? ($place->cityArea?->name_ar.' · '.$city->name_ar) : ($place->cityArea?->name_en.' · '.$city->name_en) }}</span>
            </span>
        @endif
        {{-- Photo count pill --}}
        @if($allImages->count() > 0)
            <span class="text-[13px] text-[#717171] bg-[#fafafa]" style="padding: 4px 12px; border-radius: 999px;">
                {{ $allImages->count() }} {{ $isRtl ? 'صورة' : 'photos' }}
            </span>
        @endif
        {{-- Attribute count pill --}}
        @if($place->attributeValues->count() > 0)
            <span class="text-[13px] text-[#717171] bg-[#fafafa]" style="padding: 4px 12px; border-radius: 999px;">
                {{ $place->attributeValues->count() }} {{ $isRtl ? 'مرفقاً' : 'features' }}
            </span>
        @endif
    </div>
</div>

{{-- ── AIRBNB-STYLE PHOTO MOSAIC (desktop) + mobile carousel ── --}}
@if($allImages->count() > 0)
    @php $imgCount = $allImages->count(); @endphp

    {{-- Desktop mosaic — adapts to image count (master pattern, simplified to 1/2/3/4+) --}}
    <div class="hidden sm:block relative">
        @if($imgCount === 1)
            <div class="overflow-hidden bg-[#f7f7f7]" style="height: 480px; border-radius: 28px;">
                <img src="{{ $imgUrl($allImages[0]) }}" class="w-full h-full object-cover" alt="" loading="eager">
            </div>
        @elseif($imgCount === 2)
            <div class="grid grid-cols-2 gap-2 overflow-hidden" style="height: 480px; border-radius: 28px;">
                @foreach($allImages as $img)
                    <div class="overflow-hidden bg-[#f7f7f7]"><img src="{{ $imgUrl($img) }}" class="w-full h-full object-cover" alt="" loading="lazy"></div>
                @endforeach
            </div>
        @elseif($imgCount === 3)
            <div class="overflow-hidden" style="display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(2, 1fr); gap: 8px; height: 480px; border-radius: 28px;">
                <div class="overflow-hidden bg-[#f7f7f7]" style="grid-column: span 2; grid-row: span 2;">
                    <img src="{{ $imgUrl($allImages[0]) }}" class="w-full h-full object-cover" alt="" loading="eager">
                </div>
                <div class="overflow-hidden bg-[#f7f7f7]"><img src="{{ $imgUrl($allImages[1]) }}" class="w-full h-full object-cover" alt="" loading="lazy"></div>
                <div class="overflow-hidden bg-[#f7f7f7]"><img src="{{ $imgUrl($allImages[2]) }}" class="w-full h-full object-cover" alt="" loading="lazy"></div>
            </div>
        @else
            {{-- 4+ images: 1 big + 3 small (no "+N more" button — see all photos in the gallery section below) --}}
            <div class="overflow-hidden" style="display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr); gap: 8px; height: 480px; border-radius: 28px;">
                <div class="overflow-hidden bg-[#f7f7f7]" style="grid-column: span 2; grid-row: span 2;">
                    <img src="{{ $imgUrl($allImages[0]) }}" class="w-full h-full object-cover" alt="" loading="eager">
                </div>
                @for($i = 1; $i <= 3; $i++)
                    <div class="overflow-hidden bg-[#f7f7f7]"><img src="{{ $imgUrl($allImages[$i]) }}" class="w-full h-full object-cover" alt="" loading="lazy"></div>
                @endfor
                @if($imgCount > 4)
                    @php $more = $imgCount - 4; @endphp
                    <div class="flex flex-col items-center justify-center text-white bg-[#222] {{ $fa }}">
                        <div class="text-[36px] font-bold leading-none tabular-nums">+{{ $more }}</div>
                        <div class="mt-2 text-[13px] font-semibold">{{ $isRtl ? 'صورة أخرى' : 'more photos' }}</div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Mobile horizontal scroll carousel --}}
    <div class="sm:hidden"
         x-data="{
            idx: 0,
            total: {{ $imgCount }},
            onScroll(el) { const w = el.clientWidth; if (w) this.idx = Math.round(Math.abs(el.scrollLeft) / w); },
         }">
        <div class="relative overflow-hidden bg-[#f7f7f7]" style="border-radius: 20px;">
            <div class="flex no-scrollbar"
                 style="overflow-x: auto; overflow-y: hidden; scroll-snap-type: x mandatory; aspect-ratio: 4 / 3; width: 100%;"
                 @scroll.passive="onScroll($event.target)" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                @foreach($allImages as $img)
                    <div style="width: 100%; height: 100%; flex-shrink: 0; scroll-snap-align: center;">
                        <img src="{{ $imgUrl($img) }}" style="width: 100%; height: 100%; object-fit: cover;" alt="" draggable="false" loading="lazy">
                    </div>
                @endforeach
            </div>
            @if($imgCount > 1)
                <div class="absolute bottom-3 {{ $isRtl ? 'left-3' : 'right-3' }} text-white text-xs font-bold pointer-events-none"
                     style="background: rgba(0,0,0,0.6); padding: 5px 11px; border-radius: 999px; backdrop-filter: blur(8px);" dir="ltr">
                    <span x-text="idx + 1"></span> <span class="opacity-60">/</span> {{ $imgCount }}
                </div>
            @endif
        </div>
    </div>
@endif

{{-- ── BODY SECTIONS ── --}}
<div style="margin-top: 48px;">

    {{-- DESCRIPTION (master pattern, inline show-more) --}}
    @if($place->localized_description)
        <section class="{{ $start }} border-b border-[#ebebeb]" style="padding-bottom: 48px;"
                 x-data="{ expanded: false }">
            <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 20px;">
                {{ $isRtl ? 'الوصف' : 'About this place' }}
            </h2>
            <p class="text-[16px] text-[#222] {{ $fa }}"
               :style="expanded
                   ? 'line-height: 1.7; white-space: pre-line;'
                   : 'line-height: 1.7; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 4; line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;'">{{ $place->localized_description }}</p>
            @if(mb_strlen($place->localized_description) > 200)
                <button type="button" @click="expanded = !expanded"
                        class="w-full flex items-center justify-center font-semibold text-[#222] hover:bg-[#ebebeb] transition-colors {{ $fa }}"
                        style="margin-top: 20px; padding: 14px 20px; background-color: #f7f7f7; border-radius: 14px;">
                    <span x-show="!expanded">{{ $isRtl ? 'عرض المزيد' : 'Show more' }}</span>
                    <span x-show="expanded" x-cloak>{{ $isRtl ? 'عرض أقل' : 'Show less' }}</span>
                </button>
            @endif
        </section>
    @endif

    {{-- ── PRICE — NEW (master didn't have one because it didn't store prices) ── --}}
    <section class="border-b border-[#ebebeb]" style="padding-top: 48px; padding-bottom: 48px;">
        <div class="flex items-end justify-between flex-wrap" style="gap: 16px;">
            <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}">
                {{ $isRtl ? 'السعر' : 'Pricing' }}
            </h2>
            <div class="text-end {{ $fa }}">
                <div class="text-[28px] sm:text-[36px] font-bold text-[#222] tabular-nums" dir="ltr">
                    {{ number_format($place->price) }} <span class="text-[14px] text-[#717171]">SAR</span>
                </div>
                <div class="text-[12px] text-[#717171]">{{ $isRtl ? 'السعر الأساسي / ليلة' : 'Base price / night' }}</div>
            </div>
        </div>

        @if($hasPerDay)
            <div style="margin-top: 28px;">
                <div class="text-[13px] font-semibold text-[#717171] {{ $fa }}" style="margin-bottom: 10px;">
                    {{ $isRtl ? 'السعر لكل يوم' : 'Per-day pricing' }}
                </div>
                <div class="grid grid-cols-7" style="gap: 8px;">
                    @foreach($dayLabels as $day => $label)
                        @php
                            $p = $dayPrices[$day] > 0 ? $dayPrices[$day] : (int) $place->price;
                            $isCustom = $dayPrices[$day] > 0 && $dayPrices[$day] !== (int) $place->price;
                        @endphp
                        <div class="text-center" style="padding: 12px 6px; border-radius: 14px; background-color: {{ $isCustom ? '#fff1ef' : '#fafafa' }};">
                            <div class="text-[11px] font-bold uppercase {{ $isCustom ? 'text-[#F88379]' : 'text-[#717171]' }} {{ $fa }}">{{ $label }}</div>
                            <div class="text-[15px] font-bold text-[#222] tabular-nums" dir="ltr" style="margin-top: 4px;">{{ number_format($p) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- ── CHECK-IN / CHECK-OUT — NEW (master didn't have these times) ── --}}
    <section class="border-b border-[#ebebeb]" style="padding-top: 48px; padding-bottom: 48px;">
        <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}" style="margin-bottom: 24px;">
            {{ $isRtl ? 'مواعيد الإقامة' : 'Check-in & check-out' }}
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
            <div class="flex items-center bg-white {{ $start }}" style="gap: 16px; padding: 20px; border-radius: 20px; border: 1px solid #ebebeb;">
                <span class="flex items-center justify-center bg-[#f7f7f7] shrink-0"
                      style="width: 48px; height: 48px; border-radius: 16px; font-size: 22px;">🕒</span>
                <div class="{{ $fa }}">
                    <div class="text-[12px] font-semibold text-[#717171] uppercase tracking-wider">{{ $isRtl ? 'الوصول' : 'Check-in' }}</div>
                    <div class="text-[20px] font-bold text-[#222] tabular-nums" dir="ltr" style="margin-top: 2px;">{{ $place->check_in_time }}</div>
                </div>
            </div>
            <div class="flex items-center bg-white {{ $start }}" style="gap: 16px; padding: 20px; border-radius: 20px; border: 1px solid #ebebeb;">
                <span class="flex items-center justify-center bg-[#f7f7f7] shrink-0"
                      style="width: 48px; height: 48px; border-radius: 16px; font-size: 22px;">🕛</span>
                <div class="{{ $fa }}">
                    <div class="text-[12px] font-semibold text-[#717171] uppercase tracking-wider">{{ $isRtl ? 'المغادرة' : 'Check-out' }}</div>
                    <div class="text-[20px] font-bold text-[#222] tabular-nums" dir="ltr" style="margin-top: 2px;">{{ $place->check_out_time }}</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── ATTRIBUTES grouped by AttributeGroup (master's amenities/facilities, unified) ── --}}
    @if($place->attributeValues->count() > 0)
        <section class="border-b border-[#ebebeb]" style="padding-top: 48px; padding-bottom: 48px;">
            <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] mb-1 {{ $start }} {{ $fa }}">
                {{ $isRtl ? 'ما يقدمه هذا المكان' : 'What this place offers' }}
            </h2>
            <p class="text-[15px] text-[#717171] {{ $start }} {{ $fa }}" style="margin-bottom: 28px;">
                {{ $place->attributeValues->count() }} {{ $isRtl ? 'ميزة مختارة' : 'features selected' }}
            </p>

            @foreach($attributesByGroup as $groupId => $pas)
                @php $group = $pas->first()->attribute->group; @endphp
                @if($group)
                    <div style="{{ ! $loop->first ? 'margin-top: 32px; padding-top: 32px; border-top: 1px solid #ebebeb;' : '' }}">
                        <h3 class="text-[13px] font-bold text-[#717171] uppercase tracking-wider {{ $fa }}" style="margin-bottom: 16px;">
                            {{ $isRtl ? $group->name_ar : $group->name_en }}
                        </h3>
                        <ul class="{{ $start }} {{ $fa }}" style="list-style: none; padding: 0; margin: 0;">
                            @foreach($pas as $pa)
                                @php
                                    $a = $pa->attribute;
                                    $count = $pa->value && is_numeric($pa->value) ? (int) $pa->value : null;
                                @endphp
                                <li style="padding: 14px 0; border-bottom: 1px solid #f3f4f6;"
                                    x-data="{ expanded: false }">
                                    <div class="flex items-center" style="gap: 12px;">
                                        <span class="shrink-0" style="font-size: 22px; line-height: 1; width: 32px; text-align: center;">{{ $a->icon ?: '·' }}</span>
                                        <span class="text-[15px] sm:text-[16px] text-[#222] flex-1 font-medium">{{ $isRtl ? $a->name_ar : $a->name_en }}</span>
                                        @if($count !== null && $count > 1)
                                            <span class="text-[14px] font-bold text-[#222] tabular-nums" dir="ltr">×{{ $count }}</span>
                                        @endif
                                    </div>
                                    @if($pa->description)
                                        <div style="padding-{{ $isRtl ? 'right' : 'left' }}: 44px; margin-top: 6px;">
                                            <p class="text-[14px] text-[#717171]"
                                               :style="expanded
                                                   ? 'line-height: 1.6;'
                                                   : 'line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;'">{{ $pa->description }}</p>
                                            @if(mb_strlen($pa->description) > 100)
                                                <button type="button" @click="expanded = !expanded"
                                                        class="text-[13px] font-semibold text-[#222] underline underline-offset-2 hover:text-black"
                                                        style="margin-top: 4px;">
                                                    <span x-show="!expanded">{{ $isRtl ? 'عرض المزيد' : 'Show more' }}</span>
                                                    <span x-show="expanded" x-cloak>{{ $isRtl ? 'عرض أقل' : 'Show less' }}</span>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </section>
    @endif

    {{-- ── PHOTO GALLERY with per-photo linked attribute + description ── --}}
    @if($allImages->count() > 0)
        <section style="padding-top: 48px; padding-bottom: 48px;">
            <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] mb-1 {{ $start }} {{ $fa }}">
                {{ $isRtl ? 'الصور' : 'Photos' }}
            </h2>
            <p class="text-[15px] text-[#717171] {{ $start }} {{ $fa }}" style="margin-bottom: 28px;">
                {{ $allImages->count() }} {{ $isRtl ? 'صورة، مع وصف وارتباط بالمرافق' : 'photos with descriptions and linked features' }}
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3" style="gap: 16px;">
                @foreach($allImages as $p)
                    @php
                        $linkedAttr = $p->attribute;
                        $linkedPa = $linkedAttr ? $paByAttrId->get($linkedAttr->id) : null;
                    @endphp
                    <div>
                        <div class="relative">
                            <img src="{{ $imgUrl($p) }}" class="block w-full aspect-square object-cover" style="border-radius: 14px;" alt="" loading="lazy">
                            @if($p->featured_order !== null)
                                <span class="absolute top-1 inline-flex items-center gap-1 text-[10px] font-bold bg-[#F88379] text-white {{ $fa }}"
                                      style="padding: 2px 8px; border-radius: 999px; {{ $isRtl ? 'right: 4px;' : 'left: 4px;' }}">
                                    {{ $p->featured_order === 0 ? '★ ' . ($isRtl ? 'الغلاف' : 'COVER') : '#' . ($p->featured_order + 1) }}
                                </span>
                            @endif
                        </div>
                        @if($linkedAttr)
                            <div class="text-[13px] {{ $fa }}" style="margin-top: 10px;">
                                <div class="inline-flex items-center font-semibold text-[#222]" style="gap: 6px;">
                                    <span>{{ $linkedAttr->icon }}</span>
                                    <span>{{ $isRtl ? $linkedAttr->name_ar : $linkedAttr->name_en }}</span>
                                </div>
                                @if($linkedPa?->description)
                                    <p class="text-[12px] text-[#717171] leading-snug" style="margin-top: 2px;">{{ $linkedPa->description }}</p>
                                @endif
                            </div>
                        @else
                            <div class="text-[12px] text-[#bababa] {{ $fa }}" style="margin-top: 10px;">
                                {{ $isRtl ? 'صورة عامة' : 'General photo' }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── HOUSE RULES (preserved if host wrote any) ── --}}
    @if($place->localized_rules)
        <section style="padding-top: 48px; padding-bottom: 48px; border-top: 1px solid #ebebeb;">
            <h2 class="text-[22px] sm:text-2xl font-semibold text-[#222] {{ $start }} {{ $fa }}" style="margin-bottom: 20px;">
                {{ $isRtl ? 'قواعد المكان' : 'House rules' }}
            </h2>
            <p class="text-[15px] text-[#222] leading-relaxed whitespace-pre-line {{ $start }} {{ $fa }}">{{ $place->localized_rules }}</p>
        </section>
    @endif
</div>
