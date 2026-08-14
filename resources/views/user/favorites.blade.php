@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
@endphp

@section('title', ($isRtl ? 'المفضلة' : 'Wishlist').' — Calm')

@section('body')
<div class="min-h-screen bg-white" @if($me) x-data="calmFavorites()" x-init="fetchPage(1)" @endif>

    {{-- Fixed (sticky) page header --}}
    <header class="sticky top-0 z-30" style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="mx-auto w-full" style="max-width: 720px; padding: 18px 20px;">
            <h1 class="font-bold text-black {{ $fa }}" style="font-size: 20px; line-height: 1.3;">{{ $isRtl ? 'الـمفضـلـة' : 'Wishlist' }}</h1>
        </div>
    </header>

    <main class="mx-auto w-full" style="max-width: 720px; padding: 6px 20px 36px;">

        @if(! $me)
            @include('partials._web_login_prompt', [
                'promptTitle' => $isRtl ? 'سجّل دخولك' : 'Sign in',
                'promptSubtitle' => $isRtl ? 'سجّل دخولك لعرض الأماكن المحفوظة في مفضلتك.' : 'Sign in to see your saved places.',
            ])
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 32px 24px; margin-top: 24px;">
                <template x-for="p in items" :key="p.id">
                    @include('partials._web_card_template')
                </template>
            </div>

            {{-- Loading --}}
            <div x-show="loadingGrid && items.length === 0"
                 class="grid grid-cols-1 sm:grid-cols-2" style="gap: 32px 24px; margin-top: 24px;">
                @for($sk = 0; $sk < 2; $sk++)
                    <div>
                        <div class="calm-skeleton" style="border-radius: 24px; aspect-ratio: 1;"></div>
                        <div class="calm-skeleton" style="height: 14px; border-radius: 4px; width: 72%; margin-top: 12px;"></div>
                        <div class="calm-skeleton" style="height: 14px; border-radius: 4px; width: 50%; margin-top: 8px;"></div>
                    </div>
                @endfor
            </div>

            {{-- Empty state --}}
            <div x-show="!loadingGrid && !gridError && items.length === 0" x-cloak class="text-center" style="padding: 70px 0;">
                <div style="font-size: 40px; margin-bottom: 12px;">🤍</div>
                <p class="font-bold text-black {{ $fa }}" style="font-size: 17px;">{{ $isRtl ? 'لا توجد أماكن محفوظة بعد' : 'No saved places yet' }}</p>
                <p class="{{ $fa }}" style="font-size: 13px; margin-top: 4px; color: #AAAAAA;">
                    {{ $isRtl ? 'اضغط على القلب في أي مكان يعجبك ليظهر هنا.' : 'Tap the heart on any place you like and it will show up here.' }}
                </p>
                <a href="{{ route('landing') }}"
                   class="calm-press inline-flex items-center font-bold text-white {{ $fa }}"
                   style="margin-top: 18px; padding: 13px 28px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; font-size: 14px; background-color: #000;">
                    {{ $isRtl ? 'استكشف الأماكن' : 'Explore places' }}
                </a>
            </div>
            <div x-show="gridError" x-cloak class="text-center text-[#DC2626] {{ $fa }}" style="padding: 50px 0;">
                {{ $isRtl ? 'حدث خطأ — أعد المحاولة.' : 'Something went wrong — please retry.' }}
            </div>

            {{-- Load more --}}
            <div class="text-center" style="margin-top: 32px;" x-show="hasMore && !gridError" x-cloak>
                <button type="button" @click="loadMore()" :disabled="loadingGrid"
                        class="calm-press inline-flex items-center font-bold text-white hover:opacity-90 disabled:opacity-60 transition-opacity {{ $fa }}"
                        style="padding: 13px 34px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; gap: 8px; font-size: 14px; background-color: #000;">
                    <span>{{ $isRtl ? 'عرض المزيد' : 'Load more' }}</span>
                </button>
            </div>
        @endif
    </main>

    @include('partials._web_floating_nav')
    @include('partials._web_footer')
</div>

@if($me)
    @include('partials._web_grid_js')
    <script>
        function calmFavorites() {
            return { ...calmGrid('/api/favorites') };
        }
    </script>
@endif
<style>[x-cloak] { display: none !important; }</style>
@endsection
