@extends('layouts.app')

@section('title', 'Calm — ' . $host->slug)

@section('body')
@php
    use App\Support\Catalog;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $arabicClass = $isRtl ? 'font-arabic' : '';
    $heroImages = $host->images->values();
    $extraImages = $host->images->whereNull('host_facility_id');
    $placeLabel = Catalog::placeTypeLabel($host->place_type, $locale);
@endphp

<div class="min-h-screen bg-white" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

    {{-- top header (logo + language) --}}
    <header class="w-full border-b border-[#ebebeb] sticky top-0 bg-white/90 backdrop-blur z-30">
        <div class="px-5 sm:px-10 lg:px-20 h-16 sm:h-20 flex items-center justify-between max-w-7xl mx-auto">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-8 sm:h-10 w-auto" draggable="false">
            </a>
            <form method="POST" action="{{ url('/locale/' . ($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                @csrf
                <button type="submit"
                        class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] transition-colors"
                        style="padding: 10px 18px; border-radius: 999px; corner-shape: squircle;">
                    {{ $locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-5xl mx-auto w-full">

        {{-- hero gallery: app-style on mobile (single image + counter), collage on desktop --}}
        @if($heroImages->count() > 0)
            {{-- mobile carousel --}}
            <div class="sm:hidden relative"
                 x-data="{ idx: 0, total: {{ $heroImages->count() }} }">
                <div class="relative overflow-hidden bg-[#f7f7f7]" style="aspect-ratio: 4 / 5;">
                    @foreach($heroImages as $i => $img)
                        <img src="{{ $img->url }}"
                             class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300"
                             :class="idx === {{ $i }} ? 'opacity-100' : 'opacity-0'"
                             alt="">
                    @endforeach

                    {{-- counter pill --}}
                    <div class="absolute bottom-4 {{ $isRtl ? 'left-4' : 'right-4' }} text-white text-xs font-semibold"
                         style="background: rgba(0,0,0,0.55); padding: 6px 12px; border-radius: 999px; corner-shape: squircle;"
                         dir="ltr">
                        <span x-text="idx + 1"></span> / {{ $heroImages->count() }}
                    </div>

                    {{-- tap zones for next/prev --}}
                    <button type="button"
                            class="absolute inset-y-0 {{ $isRtl ? 'right-0' : 'left-0' }} w-1/3"
                            @click="idx = (idx - 1 + total) % total"
                            aria-label="prev"></button>
                    <button type="button"
                            class="absolute inset-y-0 {{ $isRtl ? 'left-0' : 'right-0' }} w-1/3"
                            @click="idx = (idx + 1) % total"
                            aria-label="next"></button>
                </div>

                {{-- dot indicators --}}
                @if($heroImages->count() > 1 && $heroImages->count() <= 8)
                    <div class="flex justify-center gap-1.5 py-3" dir="ltr">
                        @foreach($heroImages as $i => $img)
                            <button type="button"
                                    @click="idx = {{ $i }}"
                                    class="w-1.5 h-1.5 transition-all"
                                    :class="idx === {{ $i }} ? 'bg-[#222] w-4' : 'bg-[#dddddd]'"
                                    style="border-radius: 999px; corner-shape: squircle;"
                                    aria-label="image {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- desktop collage --}}
            <div class="hidden sm:block px-5 sm:px-10 lg:px-0 pt-8">
                <div class="grid grid-cols-4 gap-2 overflow-hidden h-[480px]"
                     style="border-radius: 24px; corner-shape: squircle;">
                    @php $imgs = $heroImages->take(5)->values(); @endphp
                    <div class="col-span-2 row-span-2 h-full">
                        <img src="{{ $imgs[0]->url }}" class="w-full h-full object-cover" alt="">
                    </div>
                    @for($i = 1; $i < 5; $i++)
                        @if(isset($imgs[$i]))
                            <div>
                                <img src="{{ $imgs[$i]->url }}" class="w-full h-full object-cover" alt="">
                            </div>
                        @else
                            <div class="bg-[#f7f7f7]"></div>
                        @endif
                    @endfor
                </div>
            </div>
        @endif

        {{-- content card (overlaps the hero on mobile, flat on desktop) --}}
        <div class="bg-white relative -mt-8 sm:mt-0 z-10"
             style="border-radius: 28px 28px 0 0; corner-shape: squircle;">
            <div class="px-6 sm:px-10 lg:px-0 pt-8 sm:pt-12 pb-32 sm:pb-20">

                {{-- title --}}
                <h1 class="text-[26px] sm:text-[34px] font-bold tracking-tight text-[#222] text-center sm:text-{{ $isRtl ? 'right' : 'left' }} {{ $arabicClass }}">
                    {{ $placeLabel }}
                </h1>

                {{-- facility summary (city · area · guests style line from the app) --}}
                @if($host->facilities->count() > 0)
                    <div class="mt-2 text-[15px] text-[#717171] text-center sm:text-{{ $isRtl ? 'right' : 'left' }} {{ $arabicClass }}">
                        @foreach($host->facilities as $f)
                            <span>{{ $f->count }} {{ Catalog::facilityLabel($f->key, $locale) }}</span>
                            @if(!$loop->last)<span class="mx-1.5 text-[#dddddd]">·</span>@endif
                        @endforeach
                    </div>
                @endif

                {{-- divider --}}
                <div class="my-10 border-t border-[#ebebeb]"></div>

                {{-- amenities --}}
                <section>
                    <h2 class="text-[20px] sm:text-[22px] font-bold text-[#222] mb-6 text-{{ $isRtl ? 'right' : 'left' }} {{ $arabicClass }}">
                        {{ __('amenities') }}
                    </h2>
                    @if($host->amenities->count() > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                            @foreach($host->amenities as $a)
                                <div class="flex items-center gap-3 py-1.5">
                                    <span class="text-2xl leading-none w-8 text-center shrink-0">{{ Catalog::amenityEmoji($a->key) ?: '·' }}</span>
                                    <span class="text-[15px] text-[#222] {{ $arabicClass }}">{{ Catalog::amenityLabel($a->key, $locale) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-[#717171] text-sm {{ $arabicClass }}">{{ __('no_amenities') }}</p>
                    @endif
                </section>

                {{-- per-facility photo galleries --}}
                @foreach($host->facilities as $f)
                    @php $imgs = $f->images; @endphp
                    @if($imgs->count() > 0)
                        <div class="my-10 border-t border-[#ebebeb]"></div>
                        <section>
                            <div class="flex items-baseline gap-3 mb-5 {{ $arabicClass }}">
                                <h3 class="text-[20px] sm:text-[22px] font-bold text-[#222]">
                                    {{ Catalog::facilityLabel($f->key, $locale) }}
                                </h3>
                                <span class="text-sm text-[#717171]">·</span>
                                <span class="text-sm text-[#717171]">{{ $f->count }}</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @foreach($imgs as $img)
                                    <img src="{{ $img->url }}"
                                         class="w-full aspect-square object-cover"
                                         style="border-radius: 20px; corner-shape: squircle;"
                                         alt="">
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach

                {{-- extra photos --}}
                @if($extraImages->count() > 0)
                    <div class="my-10 border-t border-[#ebebeb]"></div>
                    <section>
                        <h3 class="text-[20px] sm:text-[22px] font-bold text-[#222] mb-5 text-{{ $isRtl ? 'right' : 'left' }} {{ $arabicClass }}">
                            {{ __('photos') }}
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($extraImages as $img)
                                <img src="{{ $img->url }}"
                                     class="w-full aspect-square object-cover"
                                     style="border-radius: 20px; corner-shape: squircle;"
                                     alt="">
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </main>
</div>
@endsection
