@extends('layouts.user')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'المفضلة' : 'Favorites')
@section('heading', $isRtl ? 'المفضلة' : 'Favorites')

@section('main')
    @include('user._empty_state', [
        'icon' => '🤍',
        'title' => $isRtl ? 'لا توجد مفضلات بعد' : 'No favorites yet',
        'subtitle' => $isRtl ? 'احفظ الأماكن التي تحبها لتجدها بسهولة لاحقاً.' : 'Save places you love to find them again later.',
    ])
@endsection
