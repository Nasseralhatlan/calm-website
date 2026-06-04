@extends('layouts.user')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'حجوزاتي' : 'My bookings')
@section('heading', $isRtl ? 'حجوزاتي' : 'My bookings')

@section('main')
    @include('user._empty_state', [
        'icon' => '🎟️',
        'title' => $isRtl ? 'لم تحجز بعد' : "You haven't booked anywhere yet",
        'subtitle' => $isRtl ? 'استكشف الأماكن واحجز إقامتك القادمة.' : 'Explore places and book your next stay.',
    ])
@endsection
