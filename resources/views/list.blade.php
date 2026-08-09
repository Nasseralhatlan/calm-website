@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $listName = ($isRtl ? $list->name_ar : $list->name_en).($list->icon ? ' '.$list->icon : '');
@endphp

@section('title', ($isRtl ? $list->name_ar : $list->name_en).' — Calm')

@section('body')
<div class="min-h-screen bg-white">

    {{-- Sticky header: back + list title --}}
    <header class="sticky top-0 z-30" style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="mx-auto w-full flex items-center justify-between" style="max-width: 720px; padding: 14px 20px; gap: 10px;">
            <a href="{{ route('landing') }}" aria-label="{{ $isRtl ? 'رجوع' : 'Back' }}"
               class="calm-press shrink-0 flex items-center justify-center text-black" style="width: 40px; height: 40px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                     style="{{ $isRtl ? '' : 'transform: scaleX(-1);' }}">
                    <path d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
            <h1 class="flex-1 min-w-0 text-center font-bold text-black truncate {{ $fa }}" style="font-size: 18px; line-height: 1.3;">{{ $listName }}</h1>
            <span class="shrink-0" style="width: 40px;"></span>
        </div>
    </header>

    <main class="mx-auto w-full" style="max-width: 720px; padding: 16px 20px 36px;">
        @if($isRtl ? $list->description_ar : $list->description_en)
            <p class="{{ $fa }}" style="font-size: 13px; margin-bottom: 18px; color: #AAAAAA;">{{ $isRtl ? $list->description_ar : $list->description_en }}</p>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 32px 24px;">
            @foreach($list->places as $p)
                @include('partials._web_place_card', ['p' => $p, 'compact' => false])
            @endforeach
        </div>
    </main>

    @include('partials._web_floating_nav')
    @include('partials._web_footer')
</div>
@endsection
