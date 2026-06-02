@extends('layouts.app')

@section('title', 'Calm — Host registration')

@section('body')
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $arabicClass = $isRtl ? 'font-arabic' : '';
    $dirAttr = $isRtl ? 'rtl' : 'ltr';

    $alpineFacilities = collect($facilities)->map(fn($f) => [
        'key' => $f['key'],
        'label' => $f[$locale] ?? $f['en'],
    ])->values();

    $alpineAmenityGroups = collect($amenityGroups)->map(fn($g) => [
        'label' => $g[$locale] ?? $g['en'],
        'items' => collect($g['items'])->map(fn($a) => [
            'key' => $a['key'],
            'label' => $a[$locale] ?? $a['en'],
            'emoji' => $a['emoji'] ?? '',
        ])->values(),
    ])->values();
@endphp

<div class="min-h-screen flex flex-col bg-white" dir="{{ $dirAttr }}">
    {{-- header --}}
    <header class="w-full border-b border-[#ebebeb] sticky top-0 bg-white/90 backdrop-blur z-30">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
            </a>
            <form method="POST" action="{{ url('/locale/' . ($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                @csrf
                <button type="submit"
                    style="border-radius: 14px;"
                    class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] px-4 py-3 transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                    {{ $locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </form>
        </div>
    </header>

    <main class="flex-1 flex justify-center px-5 sm:px-8 py-10 sm:py-14" dir="{{ $dirAttr }}">
        {{-- Pass the catalog data through a JSON script tag (safe from HTML attribute escaping) --}}
        <script id="register-init-data" type="application/json">
            {!! json_encode([
                'facilities'    => $alpineFacilities,
                'amenityGroups' => $alpineAmenityGroups,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}
        </script>

        <div
            class="w-full max-w-2xl"
            x-data="registerWizard()"
            x-cloak
        >
            {{-- progress bar --}}
            <div class="mb-12">
                <div class="h-1.5 w-full bg-[#ebebeb] rounded-full overflow-hidden">
                    <div class="h-full bg-[#F88379] rounded-full transition-all duration-500"
                         :style="`width: ${(step / totalSteps) * 100}%`"></div>
                </div>
                <div class="mt-3 text-xs text-[#717171] font-medium {{ $arabicClass }}">
                    <span x-text="step"></span> / <span x-text="totalSteps"></span>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-8 r-ios-lg bg-[#fef3f2] border border-[#7a2018]/20 p-5 text-sm text-[#7a2018] {{ $arabicClass }}">
                    <div class="font-semibold mb-2">{{ $isRtl ? 'تعذّر الإرسال' : 'Could not submit' }}</div>
                    @foreach ($errors->all() as $err)
                        <div>· {{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('hosts.store') }}"
                enctype="multipart/form-data"
                @submit="submitting = true"
                x-ref="form"
                dir="{{ $dirAttr }}"
            >
                @csrf
                <input type="hidden" name="primary_image" :value="primaryImageMarker()">

                {{-- step 1: phone --}}
                <section x-show="step === 1" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $arabicClass }}">{{ __('step_phone_title') }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $arabicClass }}">{{ __('step_phone_sub') }}</p>

                    <label class="block mt-10">
                        <span class="text-sm font-semibold text-[#222] {{ $arabicClass }}">{{ __('phone_label') }}</span>
                        <div class="mt-3 flex items-stretch border border-[#dddddd] focus-within:border-[#222] transition-all overflow-hidden bg-white shadow-card r-ios-lg">
                            <span class="px-4 flex items-center text-[#717171] bg-[#f7f7f7] select-none font-medium" dir="ltr">+966</span>
                            <input
                                name="phone"
                                x-model="phone"
                                @input="phone = phone.replace(/\D/g, '').slice(0, 9)"
                                inputmode="numeric"
                                pattern="5[0-9]{8}"
                                maxlength="9"
                                minlength="9"
                                title="Saudi mobile, starts with 5, 9 digits total"
                                placeholder="{{ __('phone_placeholder') }}"
                                class="flex-1 px-4 py-4 outline-none bg-transparent text-base"
                                required
                                dir="ltr"
                                style="text-align: {{ $isRtl ? 'right' : 'left' }};"
                            >
                        </div>
                    </label>
                </section>

                {{-- step 2: place type --}}
                <section x-show="step === 2" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $arabicClass }}">{{ __('step_type_title') }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $arabicClass }}">{{ __('step_type_sub') }}</p>

                    <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        @foreach($placeTypes as $type)
                            <label
                                class="cursor-pointer r-ios-lg bg-white p-6 transition-all shadow-card shadow-card-hover flex flex-col items-start gap-4 min-h-[150px] border-2"
                                :class="placeType === '{{ $type['key'] }}' ? 'border-[#222]' : 'border-transparent'"
                            >
                                <input type="radio" name="place_type" value="{{ $type['key'] }}" x-model="placeType" class="sr-only">
                                <div class="text-4xl">
                                    @if($type['key'] === 'chalet')
                                        🏡
                                    @elseif($type['key'] === 'resthouse')
                                        🛋️
                                    @else
                                        ⛺
                                    @endif
                                </div>
                                <div class="font-semibold text-[#222] {{ $arabicClass }}">{{ $type[$locale] ?? $type['en'] }}</div>
                            </label>
                        @endforeach
                    </div>
                </section>

                {{-- step 3: basics (title + description + max guests) --}}
                <section x-show="step === 3" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $arabicClass }}">{{ __('step_basics_title') }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $arabicClass }}">{{ __('step_basics_sub') }}</p>

                    {{-- title --}}
                    <label class="block mt-10">
                        <span class="text-sm font-semibold text-[#222] {{ $arabicClass }}">{{ __('title_label') }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <input
                                name="title"
                                x-model="title"
                                type="text"
                                maxlength="120"
                                placeholder="{{ __('title_placeholder') }}"
                                class="w-full bg-transparent outline-none text-[17px] text-[#222] py-4 px-5 {{ $arabicClass }}"
                            >
                        </div>
                    </label>

                    {{-- description --}}
                    <label class="block mt-8">
                        <span class="text-sm font-semibold text-[#222] {{ $arabicClass }}">{{ __('description_label') }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <textarea
                                name="description"
                                x-model="description"
                                maxlength="5000"
                                rows="6"
                                placeholder="{{ __('description_placeholder') }}"
                                class="w-full bg-transparent outline-none resize-none text-[16px] text-[#222] py-4 px-5 leading-relaxed {{ $arabicClass }}"
                            ></textarea>
                        </div>
                        <div class="mt-1.5 text-xs text-[#717171] tabular-nums {{ $isRtl ? 'text-left' : 'text-right' }}">
                            <span x-text="(description || '').length"></span> / 5000
                        </div>
                    </label>

                    {{-- max guests --}}
                    <div class="mt-8">
                        <div class="flex items-center justify-between bg-white shadow-card r-ios-lg px-5 py-4 border border-[#dddddd]">
                            <div class="{{ $arabicClass }}">
                                <div class="text-sm font-semibold text-[#222]">{{ __('guests_label') }}</div>
                                <div class="text-xs text-[#717171] mt-0.5">{{ __('guests_sub') }}</div>
                            </div>
                            <div class="flex items-center gap-4">
                                <button type="button"
                                        @click="maxGuests = Math.max(1, (parseInt(maxGuests) || 1) - 1)"
                                        :disabled="(parseInt(maxGuests) || 1) <= 1"
                                        class="cursor-pointer w-9 h-9 rounded-full border border-[#b0b0b0] text-[#222] hover:border-[#222] flex items-center justify-center transition-colors disabled:opacity-30 disabled:cursor-not-allowed text-lg leading-none">
                                    −
                                </button>
                                <span x-text="maxGuests" class="text-[18px] font-bold text-[#222] tabular-nums min-w-[2ch] text-center"></span>
                                <button type="button"
                                        @click="maxGuests = Math.min(200, (parseInt(maxGuests) || 0) + 1)"
                                        class="cursor-pointer w-9 h-9 rounded-full border border-[#b0b0b0] text-[#222] hover:border-[#222] flex items-center justify-center transition-colors text-lg leading-none">
                                    +
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="max_guests" :value="maxGuests">
                    </div>
                </section>

                {{-- step 4: location (paste Google Maps URL) --}}
                <section x-show="step === 4" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $arabicClass }}">{{ __('step_location_title') }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $arabicClass }}">{{ __('step_location_sub') }}</p>

                    {{-- Maps URL paste --}}
                    <div class="block mt-10">
                        <label class="text-sm font-semibold text-[#222] {{ $arabicClass }}">{{ __('maps_url_label') }}</label>
                        <div class="mt-3 flex items-stretch" style="gap: 10px;">
                            <div class="flex-1 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                                <input name="maps_url"
                                       x-model="mapsUrl"
                                       type="url"
                                       maxlength="500"
                                       placeholder="{{ __('maps_url_placeholder') }}"
                                       class="w-full bg-transparent outline-none text-[15px] text-[#222] py-4 px-5"
                                       dir="ltr">
                            </div>
                            <a href="https://www.google.com/maps"
                               target="_blank"
                               rel="noopener"
                               class="shrink-0 inline-flex items-center justify-center font-semibold text-white bg-[#222] hover:bg-black active:scale-95 transition-all whitespace-nowrap {{ $arabicClass }}"
                               style="padding: 0 20px; border-radius: 14px; corner-shape: squircle; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span class="hidden sm:inline">{{ __('open_in_maps') }}</span>
                            </a>
                        </div>
                        <div class="mt-2 text-xs text-[#717171] {{ $arabicClass }}">{{ __('maps_url_hint') }}</div>
                    </div>

                    {{-- optional readable address --}}
                    <label class="block mt-8">
                        <span class="text-sm font-semibold text-[#222] {{ $arabicClass }}">{{ __('address_label') }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <input name="address"
                                   x-model="address"
                                   type="text"
                                   maxlength="255"
                                   placeholder="{{ __('address_placeholder') }}"
                                   class="w-full bg-transparent outline-none text-[16px] text-[#222] py-4 px-5 {{ $arabicClass }}">
                        </div>
                    </label>
                </section>

                {{-- step 5: facilities --}}
                <section x-show="step === 5" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $arabicClass }}">{{ __('step_facilities_title') }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $arabicClass }}">{{ __('step_facilities_sub') }}</p>

                    <div class="mt-10 flex flex-wrap gap-2">
                        <template x-for="f in facilities" :key="f.key">
                            <button
                                type="button"
                                @click="toggleFacility(f.key)"
                                style="border-radius: 999px;"
                                class="cursor-pointer px-4 py-2.5 border text-sm font-semibold transition-all"
                                :class="hasFacility(f.key)
                                    ? 'border-[#222] bg-[#222] text-white'
                                    : 'border-[#dddddd] bg-white text-[#222] hover:border-[#222]'"
                            >
                                <span x-text="f.label" class="{{ $arabicClass }}"></span>
                            </button>
                        </template>
                    </div>

                    {{-- counts + per-facility description --}}
                    <div x-show="selectedFacilities.length > 0" class="mt-10 space-y-4">
                        <p class="text-sm font-semibold text-[#222] mb-3 {{ $arabicClass }}">{{ __('how_many') }}</p>
                        <template x-for="(f, idx) in selectedFacilities" :key="f.key">
                            <div class="bg-white shadow-card r-ios-lg p-5">
                                <div class="flex items-center justify-between">
                                    <div class="font-medium text-[#222] {{ $arabicClass }}" x-text="f.label"></div>
                                    <div class="flex items-center gap-4">
                                        <button type="button" @click="f.count = Math.max(1, f.count - 1)"
                                            class="cursor-pointer w-9 h-9 rounded-full border border-[#b0b0b0] text-[#222] hover:border-[#222] flex items-center justify-center transition-colors disabled:opacity-30 disabled:cursor-not-allowed text-lg leading-none"
                                            :disabled="f.count <= 1">−</button>
                                        <span class="w-6 text-center font-medium text-[#222]" x-text="f.count"></span>
                                        <button type="button" @click="f.count = Math.min(99, f.count + 1)"
                                            class="cursor-pointer w-9 h-9 rounded-full border border-[#b0b0b0] text-[#222] hover:border-[#222] flex items-center justify-center transition-colors text-lg leading-none">+</button>
                                    </div>
                                </div>

                                {{-- optional description --}}
                                <div class="mt-3 border border-[#ebebeb] focus-within:border-[#222] transition-all r-ios-lg overflow-hidden bg-[#fafafa]">
                                    <textarea
                                        x-model="f.description"
                                        rows="2"
                                        maxlength="500"
                                        placeholder="{{ __('facility_description_placeholder') }}"
                                        class="w-full bg-transparent outline-none resize-none text-[14px] text-[#222] py-3 px-4 leading-relaxed {{ $arabicClass }}"
                                    ></textarea>
                                </div>

                                <input type="hidden" :name="`facilities[${idx}][key]`"         :value="f.key">
                                <input type="hidden" :name="`facilities[${idx}][count]`"       :value="f.count">
                                <input type="hidden" :name="`facilities[${idx}][description]`" :value="f.description">
                            </div>
                        </template>
                    </div>
                </section>

                {{-- step 6: amenities --}}
                <section x-show="step === 6" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $arabicClass }}">{{ __('step_amenities_title') }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $arabicClass }}">{{ __('step_amenities_sub') }}</p>

                    <div class="mt-10 space-y-8">
                        <template x-for="group in amenityGroups" :key="group.label">
                            <div>
                                <h3 class="text-base font-semibold text-[#222] mb-4 {{ $arabicClass }}" x-text="group.label"></h3>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="a in group.items" :key="a.key">
                                        <label class="cursor-pointer">
                                            <input type="checkbox" :value="a.key" x-model="selectedAmenities" name="amenities[]" class="sr-only peer">
                                            <span
                                                style="border-radius: 999px;"
                                                class="inline-flex items-center gap-2 px-4 py-2.5 border text-sm font-semibold transition-all
                                                       border-[#dddddd] bg-white text-[#222] hover:border-[#222]
                                                       peer-checked:border-[#222] peer-checked:bg-[#222] peer-checked:text-white"
                                            >
                                                <span x-text="a.emoji" class="text-base leading-none"></span>
                                                <span x-text="a.label" class="{{ $arabicClass }}"></span>
                                            </span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>

                {{-- step 7: images --}}
                <section x-show="step === 7" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $arabicClass }}">{{ __('step_images_title') }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $arabicClass }}">{{ __('step_images_sub') }}</p>

                    @if ($errors->any())
                        <div class="mt-6 r-ios-lg bg-[#fef3f2] p-4 text-sm text-[#7a2018] {{ $arabicClass }}">
                            @foreach ($errors->all() as $err)
                                <div>{{ $err }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div x-show="!allFacilitiesHavePhotos()" class="mt-6 r-ios-lg bg-[#fef3f2] p-4 text-sm text-[#7a2018] {{ $arabicClass }}">
                        {{ __('missing_photos_alert') }}
                    </div>

                    <div class="mt-10 space-y-4">
                        <template x-for="f in selectedFacilities" :key="f.key">
                            <div
                                class="r-ios-lg bg-white p-5 shadow-card transition-all border-2"
                                :class="(facilityFileCounts[f.key] || 0) === 0
                                    ? 'border-[#F88379]/40'
                                    : 'border-transparent'"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="font-semibold text-[#222] {{ $arabicClass }}" x-text="f.label"></div>
                                    <div class="text-xs font-medium"
                                         :class="(facilityFileCounts[f.key] || 0) === 0 ? 'text-[#F88379]' : 'text-[#1f7a3a]'">
                                        <span x-show="(facilityFileCounts[f.key] || 0) === 0" class="{{ $arabicClass }}">{{ __('photos_required_inline') }}</span>
                                        <span x-show="(facilityFileCounts[f.key] || 0) > 0">✓ <span x-text="facilityFileCounts[f.key]"></span></span>
                                    </div>
                                </div>
                                <label class="block relative cursor-pointer hover:bg-[#f7f7f7] transition-colors {{ $arabicClass }}"
                                       style="border: 2px dashed #cbd5e1; border-radius: 20px; corner-shape: squircle; padding: 28px 16px; text-align: center;">
                                    <div class="text-3xl" style="line-height: 1;">📷</div>
                                    <div class="mt-2 text-sm font-semibold text-[#222]">{{ __('add_photos') }}</div>
                                    <div class="mt-1 text-xs text-[#717171]">{{ __('add_more_photos') }}</div>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        multiple
                                        @change="onFacilityFiles($event, f.key)"
                                        class="absolute inset-0 opacity-0 cursor-pointer"
                                    >
                                </label>
                                {{-- hidden inputs that carry the already-uploaded S3 paths --}}
                                <template x-for="(u, i) in (facilityUploads[f.key] || []).filter(u => u.status === 'done')" :key="u.id">
                                    <input type="hidden" :name="`facility_image_paths[${f.key}][${i}]`" :value="u.path">
                                </template>
                                <div class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-2" x-show="(facilityUploads[f.key] || []).length > 0">
                                    <template x-for="u in facilityUploads[f.key] || []" :key="u.id">
                                        <div class="relative">
                                            <img :src="u.preview"
                                                 class="block w-full aspect-square object-cover r-ios"
                                                 :class="primaryImage === u.id ? 'ring-2 ring-[#F88379]' : ''">
                                            {{-- upload progress overlay --}}
                                            <div x-show="u.status === 'uploading'"
                                                 class="absolute inset-0 flex items-center justify-center r-ios"
                                                 style="background: rgba(255,255,255,0.6); backdrop-filter: blur(4px);">
                                                <div class="w-6 h-6 border-2 border-[#F88379] border-t-transparent rounded-full animate-spin"></div>
                                            </div>
                                            <div x-show="u.status === 'failed'"
                                                 :title="u.error || 'Upload failed'"
                                                 class="absolute inset-0 flex items-center justify-center r-ios text-white font-bold text-xs cursor-help"
                                                 style="background: rgba(180,35,24,0.85);">!</div>
                                            {{-- remove button (top-{{ $isRtl ? 'right' : 'left' }}) --}}
                                            <button type="button"
                                                    @click="removeFacilityUpload(f.key, u.id)"
                                                    title="Remove"
                                                    class="flex items-center justify-center hover:bg-white text-[#222] transition-all"
                                                    style="position: absolute; top: 6px; {{ $isRtl ? 'right' : 'left' }}: 6px; width: 28px; height: 28px; background: rgba(255,255,255,0.95); border-radius: 999px; corner-shape: squircle; box-shadow: 0 2px 6px rgba(0,0,0,0.18); z-index: 5;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                            {{-- cover star (top-{{ $isRtl ? 'left' : 'right' }}) --}}
                                            <button type="button"
                                                    x-show="u.status === 'done'"
                                                    @click="primaryImage = primaryImage === u.id ? null : u.id"
                                                    :title="primaryImage === u.id ? '{{ __('cover_photo') }}' : '{{ __('set_as_cover') }}'"
                                                    class="flex items-center justify-center transition-all"
                                                    :class="primaryImage === u.id ? 'text-white' : 'text-[#222] hover:opacity-100'"
                                                    :style="`position: absolute; top: 6px; {{ $isRtl ? 'left' : 'right' }}: 6px; width: 28px; height: 28px; background: ${primaryImage === u.id ? '#F88379' : 'rgba(255,255,255,0.95)'}; border-radius: 999px; corner-shape: squircle; box-shadow: 0 2px 6px rgba(0,0,0,0.18); z-index: 5;`">
                                                <svg width="14" height="14" viewBox="0 0 24 24" :fill="primaryImage === u.id ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- extras --}}
                        <div class="r-ios-lg bg-white border-2 border-dashed border-[#dddddd] p-5 shadow-card">
                            <div class="font-semibold text-[#222] {{ $arabicClass }}">{{ __('extra_photos') }}</div>
                            <p class="text-sm text-[#717171] mt-1 {{ $arabicClass }}">{{ __('extra_photos_sub') }}</p>
                            <label class="block mt-4 relative cursor-pointer hover:bg-[#f7f7f7] transition-colors {{ $arabicClass }}"
                                   style="border: 2px dashed #cbd5e1; border-radius: 20px; corner-shape: squircle; padding: 28px 16px; text-align: center;">
                                <div class="text-3xl" style="line-height: 1;">🖼️</div>
                                <div class="mt-2 text-sm font-semibold text-[#222]">{{ __('add_photos') }}</div>
                                <div class="mt-1 text-xs text-[#717171]">{{ __('add_more_photos') }}</div>
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    @change="onExtraFiles($event)"
                                    class="absolute inset-0 opacity-0 cursor-pointer"
                                >
                            </label>
                            {{-- hidden inputs that carry the already-uploaded S3 paths --}}
                            <template x-for="(u, i) in extraUploads.filter(u => u.status === 'done')" :key="u.id">
                                <input type="hidden" :name="`extra_image_paths[${i}]`" :value="u.path">
                            </template>
                            <div class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-2" x-show="extraUploads.length > 0">
                                <template x-for="u in extraUploads" :key="u.id">
                                    <div class="relative">
                                        <img :src="u.preview"
                                             class="block w-full aspect-square object-cover r-ios"
                                             :class="primaryImage === u.id ? 'ring-2 ring-[#F88379]' : ''">
                                        <div x-show="u.status === 'uploading'"
                                             class="absolute inset-0 flex items-center justify-center r-ios"
                                             style="background: rgba(255,255,255,0.6); backdrop-filter: blur(4px);">
                                            <div class="w-6 h-6 border-2 border-[#F88379] border-t-transparent rounded-full animate-spin"></div>
                                        </div>
                                        <div x-show="u.status === 'failed'"
                                             class="absolute inset-0 flex items-center justify-center r-ios text-white font-bold text-xs"
                                             style="background: rgba(180,35,24,0.85);">!</div>
                                        <button type="button"
                                                @click="removeExtraUpload(u.id)"
                                                title="Remove"
                                                class="absolute top-1.5 {{ $isRtl ? 'right-1.5' : 'left-1.5' }} w-7 h-7 flex items-center justify-center bg-white/90 hover:bg-white text-[#222] transition-all"
                                                style="border-radius: 999px; corner-shape: squircle; box-shadow: 0 2px 6px rgba(0,0,0,0.18);">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                        <button type="button"
                                                x-show="u.status === 'done'"
                                                @click="primaryImage = primaryImage === u.id ? null : u.id"
                                                :title="primaryImage === u.id ? '{{ __('cover_photo') }}' : '{{ __('set_as_cover') }}'"
                                                class="absolute top-1.5 {{ $isRtl ? 'left-1.5' : 'right-1.5' }} w-7 h-7 flex items-center justify-center transition-all"
                                                :class="primaryImage === u.id ? 'bg-[#F88379] text-white' : 'bg-white/90 text-[#222] hover:bg-white'"
                                                style="border-radius: 999px; corner-shape: squircle; box-shadow: 0 2px 6px rgba(0,0,0,0.18);">
                                            <svg width="14" height="14" viewBox="0 0 24 24" :fill="primaryImage === u.id ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- nav --}}
                <div class="mt-14 pt-6 border-t border-[#ebebeb] flex items-center justify-between gap-3">
                    <button
                        type="button"
                        x-show="step > 1"
                        @click="step -= 1"
                        class="btn-ghost underline underline-offset-4 {{ $arabicClass }}"
                    >
                        {{ __('back') }}
                    </button>
                    <span x-show="step === 1"></span>

                    <button
                        type="button"
                        x-show="step < totalSteps"
                        @click="next()"
                        :disabled="!canAdvance()"
                        class="btn-dark {{ $arabicClass }}"
                    >
                        {{ __('continue') }}
                    </button>

                    <button
                        type="submit"
                        x-show="step === totalSteps"
                        :disabled="submitting || !allFacilitiesHavePhotos() || !allUploadsFinished()"
                        class="btn-primary {{ $arabicClass }}"
                    >
                        <span x-show="!submitting">{{ __('submit') }}</span>
                        <span x-show="submitting">{{ __('submitting') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    // Register the component on Alpine init so it's always available
    // before the x-data attribute is evaluated.
    document.addEventListener('alpine:init', () => {
        Alpine.data('registerWizard', registerWizard);
    });

    function registerWizard() {
        let initial = {};
        try {
            const el = document.getElementById('register-init-data');
            if (el) initial = JSON.parse(el.textContent) || {};
        } catch (e) {
            console.error('Failed to parse register-init-data', e);
        }
        return {
            step: 1,
            totalSteps: 7,
            submitting: false,

            phone: '',
            placeType: '',
            title: '',
            description: '',
            maxGuests: 4,
            mapsUrl: '',
            address: '',

            facilities: initial.facilities || [],
            amenityGroups: initial.amenityGroups || [],

            selectedFacilities: [],
            selectedAmenities: [],

            facilityUploads: {},   // key -> [{ id, name, status, path, url, preview }]
            extraUploads: [],
            uploadCounter: 0,
            primaryImage: null,    // upload id of the chosen cover (number) or null

            // turn primaryImage (an id) into the position-based marker the controller expects.
            primaryImageMarker() {
                if (!this.primaryImage) return '';
                const keys = Object.keys(this.facilityUploads);
                for (const key of keys) {
                    const done = (this.facilityUploads[key] || []).filter(u => u.status === 'done');
                    const i = done.findIndex(u => u.id === this.primaryImage);
                    if (i >= 0) return 'facility_images.' + key + '.' + i;
                }
                const doneExtras = this.extraUploads.filter(u => u.status === 'done');
                const j = doneExtras.findIndex(u => u.id === this.primaryImage);
                if (j >= 0) return 'extra_images.' + j;
                return '';
            },
            removeFacilityUpload(key, id) {
                this.facilityUploads[key] = (this.facilityUploads[key] || []).filter(u => u.id !== id);
                if (this.primaryImage === id) this.primaryImage = null;
            },
            removeExtraUpload(id) {
                this.extraUploads = this.extraUploads.filter(u => u.id !== id);
                if (this.primaryImage === id) this.primaryImage = null;
            },

            hasFacility(key) {
                return this.selectedFacilities.some(f => f.key === key);
            },
            toggleFacility(key) {
                const idx = this.selectedFacilities.findIndex(f => f.key === key);
                if (idx >= 0) {
                    this.selectedFacilities.splice(idx, 1);
                } else {
                    const meta = this.facilities.find(f => f.key === key);
                    this.selectedFacilities.push({ key, label: meta.label, count: 1, description: '' });
                }
            },
            async _uploadOne(file) {
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                // Step 1: ask Laravel for a presigned PUT URL (no file bytes — tiny JSON only).
                const presignRes = await fetch('{{ route('hosts.presign-upload') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        filename: file.name,
                        mime: file.type || 'application/octet-stream',
                    }),
                });
                if (!presignRes.ok) {
                    let msg = `Could not get upload URL (${presignRes.status})`;
                    try {
                        const err = await presignRes.json();
                        if (err.message) msg = err.message;
                    } catch (_) {}
                    throw new Error(msg);
                }
                const ticket = await presignRes.json(); // { put_url, path, public_url, mime }

                // Step 2: stream the file straight to DO Spaces — PHP container is bypassed.
                const putRes = await fetch(ticket.put_url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': ticket.mime,
                        'x-amz-acl': 'public-read',
                    },
                    body: file,
                });
                if (!putRes.ok) {
                    throw new Error(`Upload to storage failed (${putRes.status})`);
                }

                return { path: ticket.path, url: ticket.public_url };
            },
            _readPreview(file) {
                return new Promise(resolve => {
                    const r = new FileReader();
                    r.onload = ev => resolve(ev.target.result);
                    r.readAsDataURL(file);
                });
            },
            async onFacilityFiles(e, key) {
                const newFiles = Array.from(e.target.files || []);
                e.target.value = ''; // allow re-picking the same file later
                if (newFiles.length === 0) return;
                if (!this.facilityUploads[key]) this.facilityUploads[key] = [];

                for (const file of newFiles) {
                    const preview = await this._readPreview(file);
                    const id = ++this.uploadCounter;
                    this.facilityUploads[key].push({
                        id,
                        name: file.name,
                        status: 'uploading',
                        preview,
                        path: null,
                        url: null,
                    });
                    // mutate via the reactive proxy by finding the row by id
                    this._uploadOne(file)
                        .then(r => {
                            const row = (this.facilityUploads[key] || []).find(u => u.id === id);
                            if (row) { row.path = r.path; row.url = r.url; row.status = 'done'; }
                        })
                        .catch((err) => {
                            const row = (this.facilityUploads[key] || []).find(u => u.id === id);
                            if (row) { row.status = 'failed'; row.error = err.message || 'Upload failed'; }
                        });
                }
            },
            async onExtraFiles(e) {
                const newFiles = Array.from(e.target.files || []);
                e.target.value = '';
                if (newFiles.length === 0) return;

                for (const file of newFiles) {
                    const preview = await this._readPreview(file);
                    const id = ++this.uploadCounter;
                    this.extraUploads.push({
                        id,
                        name: file.name,
                        status: 'uploading',
                        preview,
                        path: null,
                        url: null,
                    });
                    this._uploadOne(file)
                        .then(r => {
                            const row = this.extraUploads.find(u => u.id === id);
                            if (row) { row.path = r.path; row.url = r.url; row.status = 'done'; }
                        })
                        .catch((err) => {
                            const row = this.extraUploads.find(u => u.id === id);
                            if (row) { row.status = 'failed'; row.error = err.message || 'Upload failed'; }
                        });
                }
            },
            allFacilitiesHavePhotos() {
                if (this.selectedFacilities.length === 0) return false;
                return this.selectedFacilities.every(f =>
                    (this.facilityUploads[f.key] || []).some(u => u.status === 'done')
                );
            },
            allUploadsFinished() {
                const inProgress = (uploads) => uploads.some(u => u.status === 'uploading');
                if (inProgress(this.extraUploads)) return false;
                return Object.values(this.facilityUploads).every(arr => !inProgress(arr));
            },
            canAdvance() {
                if (this.step === 1) return /^5\d{8}$/.test(this.phone.trim());
                if (this.step === 2) return !!this.placeType;
                if (this.step === 3) return this.title.trim().length >= 2 && parseInt(this.maxGuests) >= 1;
                if (this.step === 4) return /^https?:\/\/.+/i.test(this.mapsUrl.trim());
                if (this.step === 5) return this.selectedFacilities.length > 0;
                if (this.step === 6) return true;
                return true;
            },
            next() {
                if (this.canAdvance() && this.step < this.totalSteps) {
                    this.step += 1;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },
        };
    }
</script>

<style>[x-cloak]{display:none!important}</style>
@endsection
