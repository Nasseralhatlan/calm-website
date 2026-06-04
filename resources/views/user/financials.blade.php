@extends('layouts.user')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'المالية' : 'Financials')
@section('heading', $isRtl ? 'المالية' : 'Financials')

@section('main')
    @include('user._empty_state', [
        'icon' => '💳',
        'title' => $isRtl ? 'لا توجد حركات مالية بعد' : 'No transactions yet',
        'subtitle' => $isRtl ? 'ستظهر هنا أرباحك ومدفوعاتك.' : 'Your earnings and payouts will appear here.',
    ])
@endsection
