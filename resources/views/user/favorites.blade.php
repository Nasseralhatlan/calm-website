@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', ($isRtl ? 'المفضلة' : 'Favorites').' — Calm')

@section('body')
<div class="min-h-screen bg-white" x-data="calmFavorites()" x-init="fetchPage(1)">

    @include('partials._web_topbar')

    <main class="mx-auto w-full" style="max-width: 1200px; padding: 26px 16px 110px;">
        <h1 class="text-[24px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'المفضلة' : 'Favorites' }}</h1>
        <p class="text-[13px] text-[#717171] tabular-nums {{ $fa }}" style="margin-top: 2px;"
           x-show="!loadingGrid && items.length > 0" x-cloak
           x-text="total + ' {{ $isRtl ? 'مكان محفوظ' : 'saved places' }}'"></p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap: 24px; margin-top: 20px;">
            <template x-for="p in items" :key="p.id">
                @include('partials._web_card_template')
            </template>
        </div>

        {{-- Loading --}}
        <div x-show="loadingGrid && items.length === 0" class="flex flex-col items-center text-[#717171] {{ $fa }}" style="padding: 70px 0; gap: 12px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#F88379" stroke-width="3" stroke-linecap="round">
                <path d="M21 12a9 9 0 1 1-6.2-8.56">
                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.9s" repeatCount="indefinite"/>
                </path>
            </svg>
            <span class="text-[14px]">{{ $isRtl ? 'جاري التحميل…' : 'Loading…' }}</span>
        </div>

        {{-- Empty state --}}
        <div x-show="!loadingGrid && !gridError && items.length === 0" x-cloak class="text-center" style="padding: 70px 0;">
            <div style="font-size: 40px; margin-bottom: 12px;">🤍</div>
            <p class="text-[16px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'لا توجد أماكن محفوظة بعد' : 'No saved places yet' }}</p>
            <p class="text-[13px] text-[#717171] {{ $fa }}" style="margin-top: 4px;">
                {{ $isRtl ? 'اضغط على القلب في أي مكان يعجبك ليظهر هنا.' : 'Tap the heart on any place you like and it will show up here.' }}
            </p>
            <a href="{{ route('landing') }}"
               class="inline-flex items-center font-bold text-white bg-[#222] hover:bg-black transition-colors {{ $fa }}"
               style="margin-top: 18px; padding: 12px 28px; border-radius: 18px; font-size: 14px;">
                {{ $isRtl ? 'استكشف الأماكن' : 'Explore places' }}
            </a>
        </div>
        <div x-show="gridError" x-cloak class="text-center text-[#dc2626] {{ $fa }}" style="padding: 50px 0;">
            {{ $isRtl ? 'حدث خطأ — أعد المحاولة.' : 'Something went wrong — please retry.' }}
        </div>

        {{-- Load more --}}
        <div class="text-center" style="margin-top: 28px;" x-show="hasMore && !gridError" x-cloak>
            <button type="button" @click="loadMore()" :disabled="loadingGrid"
                    class="inline-flex items-center font-bold text-white bg-[#222] hover:bg-black disabled:opacity-60 transition-colors {{ $fa }}"
                    style="padding: 13px 34px; border-radius: 18px; gap: 8px; font-size: 14px;">
                <svg x-show="loadingGrid" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                    <path d="M21 12a9 9 0 1 1-6.2-8.56">
                        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.9s" repeatCount="indefinite"/>
                    </path>
                </svg>
                <span>{{ $isRtl ? 'عرض المزيد' : 'Load more' }}</span>
            </button>
        </div>
    </main>

    @include('partials._web_floating_nav')
    @include('partials._web_footer')
</div>

@include('partials._web_grid_js')
<script>
    function calmFavorites() {
        return { ...calmGrid('/api/favorites') };
    }
</script>
<style>[x-cloak] { display: none !important; }</style>
@endsection
