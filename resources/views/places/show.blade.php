@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', ($place->title ?? '—') . ' · Calm')

@section('body')
<div class="min-h-screen bg-white" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
    <header class="w-full border-b border-[#ebebeb] sticky top-0 bg-white/90 backdrop-blur z-30">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
            </a>
        </div>
    </header>

    <main class="px-5 sm:px-10 lg:px-20" style="padding-top: 32px; padding-bottom: 64px;">
        <div class="mx-auto" style="max-width: 880px;">
            @include('places._card', ['place' => $place, 'preview' => $preview ?? false])
        </div>
    </main>
</div>
@endsection
