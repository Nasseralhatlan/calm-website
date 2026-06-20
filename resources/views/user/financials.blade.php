@extends('layouts.user')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'المالية' : 'Financials')
@section('heading', $isRtl ? 'المالية' : 'Financials')

@section('main')
    <div style="max-width: 760px; display: flex; flex-direction: column; gap: 16px;">
        {{-- Where the host's payouts are sent. --}}
        @include('partials._bank_account_form', ['user' => $user])

        {{-- Transactions feed — still a placeholder until payouts ship. --}}
        @include('user._empty_state', [
            'icon' => '💳',
            'title' => $isRtl ? 'لا توجد حركات مالية بعد' : 'No transactions yet',
            'subtitle' => $isRtl ? 'ستظهر هنا أرباحك ومدفوعاتك.' : 'Your earnings and payouts will appear here.',
        ])
    </div>
@endsection
