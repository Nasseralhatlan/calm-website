@extends('layouts.user')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'حجوزات أماكني' : 'Bookings')
@section('heading', $isRtl ? 'حجوزات أماكني' : 'Bookings on your places')

@section('main')
    @include('user._empty_state', [
        'icon' => '📅',
        'title' => $isRtl ? 'لا توجد حجوزات بعد' : 'No bookings yet',
        'subtitle' => $isRtl ? 'ستظهر هنا حجوزات الضيوف على أماكنك.' : "Guest bookings on your places will appear here.",
    ])
@endsection
