@extends('layouts.app')

@section('title', 'Calm — Become a host')

@section('body')
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $dirAttr = $isRtl ? 'rtl' : 'ltr';

    // Reshape for Alpine — only the keys the wizard needs
    $jsonPlaceTypes = $placeTypes->map(fn ($t) => [
        'id' => $t->id,
        'icon' => $t->icon,
        'label' => $isRtl ? $t->name_ar : $t->name_en,
    ])->values();

    $jsonCities = $cities->map(fn ($c) => [
        'id' => $c->id,
        'icon' => $c->avatar ?: '📍',
        'label' => $isRtl ? $c->name_ar : $c->name_en,
        'areas' => $c->areas->map(fn ($a) => [
            'id' => $a->id,
            'label' => $isRtl ? $a->name_ar : $a->name_en,
        ])->values(),
    ])->values();

    $jsonAttributeGroups = $attributeGroups->map(fn ($g) => [
        'id' => $g->id,
        'label' => $isRtl ? $g->name_ar : $g->name_en,
        'attributes' => $g->attributes->map(fn ($a) => [
            'id' => $a->id,
            'icon' => $a->icon,
            'label' => $isRtl ? $a->name_ar : $a->name_en,
            'type' => $a->type->value,           // 'boolean' or 'number'
            'photoRule' => $a->photo_rule->value, // 'none' | 'optional' | 'required'
        ])->values(),
    ])->values();

    $dayLabels = $isRtl
        ? ['sunday' => 'الأحد', 'monday' => 'الإثنين', 'tuesday' => 'الثلاثاء', 'wednesday' => 'الأربعاء', 'thursday' => 'الخميس', 'friday' => 'الجمعة', 'saturday' => 'السبت']
        : ['sunday' => 'Sun', 'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat'];

    // Draft hydrate payload (only populated when `?draft=<id>` resolved a row).
    $jsonDraft = $draft ? [
        'id' => $draft->id,
        'place_type_id' => $draft->place_type_id,
        'title' => $draft->title,
        'description' => $draft->description,
        'city_id' => $draft->cityArea?->city_id,
        'city_area_id' => $draft->city_area_id,
        'price' => $draft->price,
        'day_prices' => [
            'sunday' => $draft->price_sunday,
            'monday' => $draft->price_monday,
            'tuesday' => $draft->price_tuesday,
            'wednesday' => $draft->price_wednesday,
            'thursday' => $draft->price_thursday,
            'friday' => $draft->price_friday,
            'saturday' => $draft->price_saturday,
        ],
        'check_in_time' => $draft->check_in_time,
        'check_out_time' => $draft->check_out_time,
        'rules' => $draft->rules,
    ] : null;
@endphp

<div class="min-h-screen flex flex-col bg-white" dir="{{ $dirAttr }}">
    {{-- header --}}
    <header class="w-full border-b border-[#ebebeb] sticky top-0 bg-white/90 backdrop-blur z-30">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
            </a>
            <div class="flex items-center" style="gap: 4px;">
                <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                    @csrf
                    <button type="submit"
                            style="border-radius: 14px;"
                            class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] px-4 py-2.5 transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                        {{ $locale === 'ar' ? 'English' : 'العربية' }}
                    </button>
                </form>
                <a href="{{ route('user.places') }}"
                   style="border-radius: 14px;"
                   class="text-sm font-semibold text-[#717171] hover:text-[#222] hover:bg-[#f7f7f7] px-4 py-2.5 transition-colors {{ $fa }}">
                    {{ $isRtl ? 'إلغاء' : 'Cancel' }}
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1 flex justify-center px-5 sm:px-8 py-10 sm:py-14" dir="{{ $dirAttr }}">
        {{-- Catalog data for the wizard (JSON-in-script-tag pattern from the old wizard,
             safe from HTML attribute escaping with Arabic apostrophes) --}}
        <script id="register-init-data" type="application/json">
            {!! json_encode([
                'placeTypes'      => $jsonPlaceTypes,
                'cities'          => $jsonCities,
                'attributeGroups' => $jsonAttributeGroups,
                'draftEndpoint'   => route('host.places.draft'),
                'draft'           => $jsonDraft,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}
        </script>

        <div class="w-full max-w-2xl" x-data="registerWizard()" x-cloak>

            {{-- progress bar --}}
            <div class="mb-12">
                <div class="h-1.5 w-full bg-[#ebebeb] rounded-full overflow-hidden">
                    <div class="h-full bg-[#F88379] rounded-full transition-all duration-500"
                         :style="`width: ${(step / totalSteps) * 100}%`"></div>
                </div>
                <div class="mt-3 text-xs text-[#717171] font-medium {{ $fa }}">
                    <span x-text="step"></span> / <span x-text="totalSteps"></span>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-8 r-ios-lg bg-[#fef3f2] border border-[#7a2018]/20 p-5 text-sm text-[#7a2018] {{ $fa }}">
                    <div class="font-semibold mb-2">{{ $isRtl ? 'تعذّر الإرسال' : 'Could not submit' }}</div>
                    @foreach ($errors->all() as $err)<div>· {{ $err }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('host.places.store') }}" @submit="submitting = true" x-ref="form" dir="{{ $dirAttr }}">
                @csrf
                {{-- Carries the draft we've been auto-saving so the server promotes it
                     instead of creating a duplicate row on final submit. --}}
                <input type="hidden" name="draft_id" :value="draftId || ''">

                {{-- ── Step 1: place type ── --}}
                <section x-show="step === 1" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'ما نوع مكانك؟' : "What kind of place is it?" }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'اختر الأقرب — يمكنك إضافة التفاصيل لاحقاً.' : 'Pick the closest match — you can refine later.' }}</p>

                    <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <template x-for="t in placeTypes" :key="t.id">
                            <label class="cursor-pointer r-ios-lg bg-white p-6 transition-all shadow-card shadow-card-hover flex flex-col items-start gap-4 min-h-[150px] border-2"
                                   :class="placeTypeId === t.id ? 'border-[#222]' : 'border-transparent'">
                                <input type="radio" name="place_type_id" :value="t.id" x-model.number="placeTypeId" class="sr-only">
                                <div class="text-4xl" style="line-height: 1;" x-text="t.icon || '🏠'"></div>
                                <div class="font-semibold text-[#222] {{ $fa }}" x-text="t.label"></div>
                            </label>
                        </template>
                    </div>
                </section>

                {{-- ── Step 2: basics ── --}}
                <section x-show="step === 2" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'المعلومات الأساسية' : 'The basics' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'عنوان قصير ووصف يجذب الضيوف.' : 'A short title and a description guests will love.' }}</p>

                    <label class="block mt-10">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'العنوان' : 'Title' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <input name="title" x-model="title" type="text" maxlength="120"
                                   placeholder="{{ $isRtl ? 'شاليه فاخر بإطلالة على الجبال' : 'Luxury chalet with mountain view' }}"
                                   class="w-full bg-transparent outline-none text-[17px] text-[#222] py-4 px-5 {{ $fa }}">
                        </div>
                    </label>

                    <label class="block mt-8">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'الوصف' : 'Description' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <textarea name="description" x-model="description" maxlength="5000" rows="6"
                                      placeholder="{{ $isRtl ? 'صف ما يجعل مكانك مميزاً...' : 'Tell guests what makes your place special...' }}"
                                      class="w-full bg-transparent outline-none resize-none text-[16px] text-[#222] py-4 px-5 leading-relaxed {{ $fa }}"></textarea>
                        </div>
                        <div class="mt-1.5 text-xs text-[#717171] tabular-nums {{ $isRtl ? 'text-left' : 'text-right' }}">
                            <span x-text="(description || '').length"></span> / 5000
                        </div>
                    </label>
                </section>

                {{-- ── Step 3: city ── --}}
                <section x-show="step === 3" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'في أي مدينة؟' : 'Which city?' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'اختر مدينة وانتقل للتالي لاختيار الحي.' : 'Pick a city, then continue to choose the area.' }}</p>

                    <div class="mt-10 grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <template x-for="c in cities" :key="c.id">
                            <button type="button" @click="selectCity(c.id)"
                                    class="cursor-pointer r-ios-lg p-5 flex flex-col items-start gap-3 min-h-[120px] border-2 transition-all"
                                    :class="cityId === c.id
                                        ? 'border-[#222] bg-[#222] text-white shadow-card'
                                        : 'border-transparent bg-white shadow-card shadow-card-hover text-[#222] hover:border-[#222]'">
                                <div class="text-3xl" style="line-height: 1;" x-text="c.icon"></div>
                                <div class="font-semibold {{ $fa }}" x-text="c.label"></div>
                                <div class="text-xs {{ $fa }}"
                                     :class="cityId === c.id ? 'text-white/70' : 'text-[#717171]'">
                                    <span x-text="c.areas.length"></span>
                                    <span>{{ $isRtl ? 'حياً' : 'areas' }}</span>
                                </div>
                            </button>
                        </template>
                    </div>
                </section>

                {{-- ── Step 4: area (of the chosen city) ── --}}
                <section x-show="step === 4" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'أي حي؟' : 'Which area?' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'اختر الحي داخل المدينة.' : 'Pick the area inside your city.' }}</p>

                    {{-- Selected-city context pill --}}
                    <div x-show="cityId" class="mt-6 inline-flex items-center gap-2 bg-[#fafafa] {{ $fa }}"
                         style="padding: 8px 14px; border-radius: 999px; border: 1px solid #ebebeb;">
                        <span class="text-base" x-text="selectedCity()?.icon"></span>
                        <span class="text-sm font-semibold text-[#222]" x-text="selectedCity()?.label"></span>
                    </div>

                    <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <template x-for="a in (selectedCity()?.areas || [])" :key="a.id">
                            <button type="button" @click="cityAreaId = a.id"
                                    class="cursor-pointer r-ios-lg p-4 text-start text-sm font-semibold transition-all border-2"
                                    :class="cityAreaId === a.id
                                        ? 'border-[#222] bg-[#222] text-white shadow-card'
                                        : 'border-transparent bg-white shadow-card text-[#222] hover:border-[#222]'"
                                    x-text="a.label"></button>
                        </template>
                    </div>

                    <input type="hidden" name="city_area_id" :value="cityAreaId || ''">
                </section>

                {{-- ── Step 5: pricing ── --}}
                <section x-show="step === 5" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'التسعير' : 'Pricing' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'حدد سعراً أساسياً، ويمكنك تخصيص كل يوم.' : 'Set a base price, then optionally adjust per day.' }}</p>

                    <label class="block mt-10">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'السعر الأساسي (ريال / ليلة)' : 'Base price (SAR / night)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <input name="price" x-model.number="price" type="number" min="0"
                                   class="w-full bg-transparent outline-none text-[17px] text-[#222] tabular-nums py-4 px-5" dir="ltr">
                        </div>
                    </label>

                    <div class="mt-8">
                        <div class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'السعر لكل يوم (اختياري)' : 'Per-day pricing (optional)' }}</div>
                        <div class="text-xs text-[#717171] mt-1 {{ $fa }}">{{ $isRtl ? 'اترك 0 لاستخدام السعر الأساسي.' : 'Leave 0 to fall back to base.' }}</div>
                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                            @foreach($dayLabels as $day => $label)
                                @php $col = "price_{$day}"; @endphp
                                <div>
                                    <label class="block text-[11px] font-bold text-[#717171] uppercase tracking-wider text-center {{ $fa }}" style="margin-bottom: 4px;">{{ $label }}</label>
                                    <input type="number" name="{{ $col }}" x-model.number="dayPrices['{{ $day }}']" min="0"
                                           class="w-full bg-white border border-[#dddddd] focus:border-[#222] r-ios text-[14px] text-center tabular-nums py-2.5 px-2" dir="ltr">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ── Step 6: pick attributes (everything is a chip) ── --}}
                <section x-show="step === 6" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'مرافق وخصائص المكان' : "What's in your place?" }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'اختر ما يتوفر، وفصّل الكمية ووصف كل واحد في الخطوة التالية.' : "Pick what's available — you'll set counts and descriptions on the next step." }}</p>

                    <div class="mt-10 space-y-8">
                        <template x-for="group in attributeGroups" :key="group.id">
                            <div>
                                <h3 class="text-base font-semibold text-[#222] mb-4 {{ $fa }}" x-text="group.label"></h3>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="a in group.attributes" :key="a.id">
                                        <button type="button"
                                                @click="toggleAttribute(a.id)"
                                                style="border-radius: 999px;"
                                                class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 border-2 text-sm font-semibold transition-all"
                                                :class="hasAttribute(a.id)
                                                    ? 'border-[#222] bg-[#222] text-white shadow-card'
                                                    : 'border-[#dddddd] bg-white text-[#222] hover:border-[#222]'">
                                            {{-- Per-chip selected indicator: a leading ✓ in a white circle when picked. --}}
                                            <span x-show="hasAttribute(a.id)"
                                                  class="inline-flex items-center justify-center bg-white text-[#222] font-bold"
                                                  style="width: 16px; height: 16px; border-radius: 999px; font-size: 10px; line-height: 1;">✓</span>
                                            <span x-text="a.icon" class="text-base leading-none"></span>
                                            <span x-text="a.label" class="{{ $fa }}"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>

                {{-- ── Step 7: configure each chosen attribute (count + description) ── --}}
                <section x-show="step === 7" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'تفاصيل ما اخترته' : 'Tell us about what you picked' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'للعناصر المعدودة حدد الكمية، وأضف وصفاً قصيراً لكل عنصر إذا أحببت.' : 'Set counts for the countable ones and add a short description for any of them.' }}</p>

                    <div class="mt-10 space-y-4">
                        <template x-for="entry in selectedAttributesList()" :key="entry.id">
                            <div class="r-ios-lg bg-white shadow-card p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span x-text="entry.attribute.icon" class="text-2xl leading-none shrink-0"></span>
                                        <div class="font-semibold text-[#222] truncate {{ $fa }}" x-text="entry.attribute.label"></div>
                                    </div>

                                    {{-- Number-type attributes get a stepper here. Booleans don't. --}}
                                    <template x-if="entry.attribute.type === 'number'">
                                        <div class="flex items-center gap-3 shrink-0">
                                            <button type="button" @click="decrementCount(entry.id)" :disabled="entry.count <= 1"
                                                    class="cursor-pointer w-9 h-9 rounded-full border border-[#b0b0b0] text-[#222] hover:border-[#222] flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed text-lg leading-none">−</button>
                                            <span class="w-6 text-center text-[18px] font-bold text-[#222] tabular-nums" x-text="entry.count"></span>
                                            <button type="button" @click="incrementCount(entry.id)"
                                                    class="cursor-pointer w-9 h-9 rounded-full border border-[#b0b0b0] text-[#222] hover:border-[#222] flex items-center justify-center text-lg leading-none">+</button>
                                        </div>
                                    </template>
                                </div>

                                <div class="mt-3 border border-[#ebebeb] focus-within:border-[#222] transition-all r-ios-lg overflow-hidden bg-[#fafafa]">
                                    <textarea x-model="selectedAttributes[entry.id].description" rows="2" maxlength="500"
                                              placeholder="{{ $isRtl ? 'اكتب وصفاً قصيراً (اختياري)...' : 'Add a short description (optional)...' }}"
                                              class="w-full bg-transparent outline-none resize-none text-[14px] text-[#222] py-3 px-4 leading-relaxed {{ $fa }}"></textarea>
                                </div>
                            </div>
                        </template>

                        {{-- Empty state if no attributes were picked in step 6 --}}
                        <div x-show="Object.keys(selectedAttributes).length === 0"
                             class="r-ios-lg bg-[#fafafa] p-8 text-center text-[#717171] text-sm {{ $fa }}">
                            {{ $isRtl ? 'لم تختر شيئاً في الخطوة السابقة. عُد للخلف وحدد ما يتوفر.' : "You didn't pick anything in the previous step. Go back to choose." }}
                        </div>
                    </div>

                    {{-- Hidden inputs that carry every chosen attribute back to the server --}}
                    <template x-for="entry in selectedAttributesList()" :key="`hid-${entry.id}`">
                        <div>
                            <input type="hidden" :name="`attributes[${entry.id}][attribute_id]`" :value="entry.id">
                            <input type="hidden" :name="`attributes[${entry.id}][value]`" :value="entry.count">
                            <input type="hidden" :name="`attributes[${entry.id}][description]`" :value="entry.description">
                        </div>
                    </template>
                </section>

                {{-- ── Step 8: images per chosen attribute that needs / allows photos ── --}}
                <section x-show="step === 8" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'صور المكان' : 'Photos of your place' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'أضف صوراً للعناصر المهمة — الصور الواضحة تزيد الحجوزات.' : 'Add photos for the key spaces — clear photos book faster.' }}</p>

                    <div class="mt-10 space-y-4">
                        <template x-for="entry in photoNeedingAttributes()" :key="`img-${entry.id}`">
                            <div class="r-ios-lg bg-white shadow-card p-5 border-2"
                                 :class="entry.attribute.photoRule === 'required' && !entry.photosCount
                                     ? 'border-[#F88379]/40'
                                     : 'border-transparent'">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span x-text="entry.attribute.icon" class="text-2xl leading-none shrink-0"></span>
                                        <div class="font-semibold text-[#222] truncate {{ $fa }}" x-text="entry.attribute.label"></div>
                                    </div>
                                    <span class="text-xs font-medium {{ $fa }}"
                                          :class="entry.attribute.photoRule === 'required' ? 'text-[#F88379]' : 'text-[#717171]'">
                                        <span x-text="entry.attribute.photoRule === 'required'
                                            ? '{{ $isRtl ? 'مطلوب' : 'Required' }}'
                                            : '{{ $isRtl ? 'اختياري' : 'Optional' }}'"></span>
                                    </span>
                                </div>

                                <label class="block relative cursor-pointer hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                                       style="border: 2px dashed #cbd5e1; border-radius: 20px; padding: 28px 16px; text-align: center;">
                                    <div class="text-3xl" style="line-height: 1;">📷</div>
                                    <div class="mt-2 text-sm font-semibold text-[#222]">{{ $isRtl ? 'أضف صوراً' : 'Add photos' }}</div>
                                    <div class="mt-1 text-xs text-[#717171]">{{ $isRtl ? 'يمكنك اختيار أكثر من صورة' : 'You can pick more than one' }}</div>
                                    <input type="file" accept="image/*" multiple
                                           @change="onPhotosPicked($event, entry.id)"
                                           class="absolute inset-0 opacity-0 cursor-pointer">
                                </label>

                                {{-- Preview grid --}}
                                <div class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-2" x-show="(photoPreviews[entry.id] || []).length > 0">
                                    <template x-for="(p, pIdx) in photoPreviews[entry.id] || []" :key="`p-${entry.id}-${pIdx}`">
                                        <div class="relative">
                                            <img :src="p" class="block w-full aspect-square object-cover r-ios">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div x-show="photoNeedingAttributes().length === 0"
                             class="r-ios-lg bg-[#fafafa] p-8 text-center text-[#717171] text-sm {{ $fa }}">
                            {{ $isRtl ? 'لا يوجد عنصر مختار يتطلب صوراً — يمكنك المتابعة.' : "Nothing you picked needs photos — you can continue." }}
                        </div>

                        <p class="text-[12px] text-[#717171] text-center {{ $fa }}" x-show="photoNeedingAttributes().length > 0">
                            {{ $isRtl ? '💡 رفع الصور إلى الخادم سيُفعّل في خطوة لاحقة.' : '💡 Server upload wiring lands in a follow-up step.' }}
                        </p>
                    </div>
                </section>

                {{-- ── Step 9: check-in/out + rules + submit ── --}}
                <section x-show="step === 9" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'تفاصيل الإقامة' : 'House rules & timing' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'الوقت والقواعد التي تحدد تجربة الضيوف.' : 'Set the timing and the rules guests should follow.' }}</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-10">
                        <label>
                            <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'وقت الوصول' : 'Check-in' }}</span>
                            <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                                <input name="check_in_time" x-model="checkInTime" type="text" placeholder="15:00" dir="ltr"
                                       class="w-full bg-transparent outline-none text-[16px] tabular-nums text-[#222] py-4 px-5">
                            </div>
                        </label>
                        <label>
                            <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'وقت المغادرة' : 'Check-out' }}</span>
                            <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                                <input name="check_out_time" x-model="checkOutTime" type="text" placeholder="12:00" dir="ltr"
                                       class="w-full bg-transparent outline-none text-[16px] tabular-nums text-[#222] py-4 px-5">
                            </div>
                        </label>
                    </div>

                    <label class="block mt-8">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'قواعد المكان (اختياري)' : 'House rules (optional)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <textarea name="rules" x-model="rules" rows="4" maxlength="5000"
                                      placeholder="{{ $isRtl ? 'مثلاً: ممنوع التدخين، الحفلات بإذن مسبق...' : 'e.g. No smoking, no parties without prior approval...' }}"
                                      class="w-full bg-transparent outline-none resize-none text-[15px] text-[#222] py-4 px-5 leading-relaxed {{ $fa }}"></textarea>
                        </div>
                    </label>
                </section>

                {{-- ── Nav buttons ── --}}
                <div class="mt-12 flex items-center justify-between gap-4">
                    <button type="button" @click="back" x-show="step > 1"
                            class="px-6 py-3 text-[#717171] hover:text-[#222] font-semibold transition-colors {{ $fa }}">
                        {{ $isRtl ? '← السابق' : '← Back' }}
                    </button>
                    <div x-show="step === 1"></div>

                    <button type="button" @click="next" x-show="step < totalSteps" :disabled="!canAdvance()"
                            class="font-bold text-white bg-[#222] hover:bg-black disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.98] transition-all {{ $fa }}"
                            style="padding: 14px 28px; border-radius: 16px;">
                        {{ $isRtl ? 'التالي' : 'Continue' }}
                    </button>

                    <button type="submit" x-show="step === totalSteps" :disabled="submitting || !canAdvance()"
                            class="font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.98] transition-all {{ $fa }}"
                            style="padding: 14px 28px; border-radius: 16px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                        <span x-show="!submitting">{{ $isRtl ? 'إنشاء المكان' : 'Create place' }}</span>
                        <span x-show="submitting" x-cloak>{{ $isRtl ? 'جاري الإنشاء...' : 'Creating...' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function registerWizard() {
    const init = JSON.parse(document.getElementById('register-init-data').textContent);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    return {
        step: 1,
        totalSteps: 9,
        submitting: false,
        draftSaving: false,
        draftError: '',

        // Catalog data from the backend
        placeTypes: init.placeTypes,
        cities: init.cities,
        attributeGroups: init.attributeGroups,
        draftEndpoint: init.draftEndpoint,

        // Form state
        draftId: null,                  // server-assigned once step 1 advances
        placeTypeId: null,
        title: '',
        description: '',
        cityId: null,                   // intermediate — picked first
        cityAreaId: null,               // sent to server
        price: 0,
        dayPrices: {
            sunday: 0, monday: 0, tuesday: 0, wednesday: 0, thursday: 0, friday: 0, saturday: 0,
        },
        checkInTime: '15:00',
        checkOutTime: '12:00',
        rules: '',
        // attribute_id → { count, description }
        selectedAttributes: {},
        // attribute_id → array of preview dataURLs (UI only — server upload wiring lands later)
        photoPreviews: {},

        init() {
            // Base price → mirror into all 7 day prices.
            this.$watch('price', (val) => {
                const n = Number(val) || 0;
                Object.keys(this.dayPrices).forEach((day) => { this.dayPrices[day] = n; });
            });

            // Resume a draft if the controller handed us one via `?draft=<id>`.
            if (init.draft) {
                this.placeTypeId = init.draft.place_type_id;
                this.title       = init.draft.title || '';
                this.description = init.draft.description || '';
                this.cityId      = init.draft.city_id || null;
                this.cityAreaId  = init.draft.city_area_id || null;
                // Assign price BEFORE day prices — the watcher flattens all 7 days to the
                // base value, then we overwrite with whatever was actually persisted.
                this.price       = init.draft.price || 0;
                Object.assign(this.dayPrices, init.draft.day_prices || {});
                this.checkInTime  = init.draft.check_in_time || '15:00';
                this.checkOutTime = init.draft.check_out_time || '12:00';
                this.rules        = init.draft.rules || '';
                this.draftId      = init.draft.id;
            }
        },

        // ── Navigation
        async next() {
            if (this.step >= this.totalSteps || !this.canAdvance()) return;
            await this.saveDraft();   // persist what we have so far
            this.step++;
        },
        back() {
            if (this.step > 1) this.step--;
        },

        /**
         * Per-step rules. Returns true if the host is allowed to advance from
         * the current step. Step 8 is the final one — its rule gates `submit`.
         */
        canAdvance() {
            switch (this.step) {
                case 1: return !!this.placeTypeId;
                case 2: return this.title.trim().length > 0;
                case 3: return !!this.cityId;          // city pick
                case 4: return !!this.cityAreaId;      // area pick
                case 5: return Number(this.price) > 0; // pricing
                case 6: return Object.keys(this.selectedAttributes).length > 0;        // attribute pick
                case 7: return this.selectedAttributesList().every((e) => e.count >= 1); // configure
                case 8: return this.photoNeedingAttributes()
                    .filter((e) => e.attribute.photoRule === 'required')
                    .every((e) => (this.photoPreviews[e.id] || []).length > 0);        // photos
                case 9: return this.checkInTime.trim().length > 0 && this.checkOutTime.trim().length > 0;
                default: return true;
            }
        },

        // ── City (step 3)
        selectCity(id) {
            // Re-picking the same city keeps the area choice; switching cities clears it.
            if (this.cityId !== id) {
                this.cityAreaId = null;
            }
            this.cityId = id;
        },
        selectedCity() {
            return this.cities.find((c) => c.id === this.cityId) || null;
        },

        // ── Attributes (selection — step 6)
        hasAttribute(id) {
            return Object.prototype.hasOwnProperty.call(this.selectedAttributes, id);
        },
        toggleAttribute(id) {
            if (this.hasAttribute(id)) {
                delete this.selectedAttributes[id];
                delete this.photoPreviews[id];
            } else {
                this.selectedAttributes[id] = { count: 1, description: '' };
            }
        },

        // ── Attributes (configure — step 7)
        attributeById(id) {
            for (const g of this.attributeGroups) {
                const a = g.attributes.find((x) => x.id === id);
                if (a) return a;
            }
            return null;
        },
        selectedAttributesList() {
            return Object.keys(this.selectedAttributes).map((rawId) => {
                const id = Number(rawId);
                const entry = this.selectedAttributes[rawId];
                return {
                    id,
                    attribute: this.attributeById(id),
                    count: entry.count,
                    description: entry.description,
                    photosCount: (this.photoPreviews[id] || []).length,
                };
            }).filter((e) => e.attribute);
        },
        incrementCount(id) {
            if (this.selectedAttributes[id]) {
                this.selectedAttributes[id].count = (this.selectedAttributes[id].count || 1) + 1;
            }
        },
        decrementCount(id) {
            if (this.selectedAttributes[id] && this.selectedAttributes[id].count > 1) {
                this.selectedAttributes[id].count -= 1;
            }
        },

        // ── Photos (step 8)
        photoNeedingAttributes() {
            return this.selectedAttributesList().filter(
                (e) => e.attribute.photoRule === 'required' || e.attribute.photoRule === 'optional'
            );
        },
        onPhotosPicked(event, attributeId) {
            const files = Array.from(event.target.files || []);
            if (!files.length) return;
            if (!this.photoPreviews[attributeId]) this.photoPreviews[attributeId] = [];
            files.forEach((file) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.photoPreviews[attributeId] = [
                        ...(this.photoPreviews[attributeId] || []),
                        e.target.result,
                    ];
                };
                reader.readAsDataURL(file);
            });
            event.target.value = '';
        },

        // ── Draft auto-save
        /**
         * Persist current wizard state to the server as a Draft place. Called
         * after every `next()`. Silent on failure (just logs) — the wizard
         * keeps working from in-memory state and the final submit still works.
         */
        async saveDraft() {
            if (!this.placeTypeId) return;   // nothing to save yet (haven't passed step 1)
            this.draftSaving = true;
            this.draftError = '';

            const payload = {
                draft_id: this.draftId,
                place_type_id: this.placeTypeId,
                title: this.title || null,
                description: this.description || null,
                city_area_id: this.cityAreaId || null,
                price: Number(this.price) || 0,
                check_in_time: this.checkInTime || '15:00',
                check_out_time: this.checkOutTime || '12:00',
                rules: this.rules || null,
                price_sunday:    this.dayPrices.sunday    || 0,
                price_monday:    this.dayPrices.monday    || 0,
                price_tuesday:   this.dayPrices.tuesday   || 0,
                price_wednesday: this.dayPrices.wednesday || 0,
                price_thursday:  this.dayPrices.thursday  || 0,
                price_friday:    this.dayPrices.friday    || 0,
                price_saturday:  this.dayPrices.saturday  || 0,
            };

            try {
                const res = await fetch(this.draftEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                if (!res.ok) throw new Error('Draft save failed (HTTP ' + res.status + ')');
                const data = await res.json();
                if (data && data.id) this.draftId = data.id;
            } catch (e) {
                console.warn('[wizard] draft save failed:', e);
                this.draftError = e.message || 'Draft save failed';
            } finally {
                this.draftSaving = false;
            }
        },
    };
}
</script>
@endsection
