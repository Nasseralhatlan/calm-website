@extends('layouts.user')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', $isRtl ? 'الملف الشخصي' : 'Profile')
@section('heading', $isRtl ? 'الملف الشخصي' : 'Profile')

@section('main')
    <div class="bg-white"
         style="padding: 28px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <dl class="divide-y divide-[#ebebeb]">
            <div class="flex items-center justify-between" style="padding: 14px 0;">
                <dt class="text-[13px] font-semibold text-[#717171] uppercase tracking-wider">{{ $isRtl ? 'الاسم' : 'Name' }}</dt>
                <dd class="text-[15px] text-[#222]">{{ $user->name ?: '—' }}</dd>
            </div>
            <div class="flex items-center justify-between" style="padding: 14px 0;">
                <dt class="text-[13px] font-semibold text-[#717171] uppercase tracking-wider">{{ $isRtl ? 'رقم الجوال' : 'Phone' }}</dt>
                <dd class="text-[15px] text-[#222]" dir="ltr">{{ $user->phone ? '+966 '.$user->phone : '—' }}</dd>
            </div>
            <div class="flex items-center justify-between" style="padding: 14px 0;">
                <dt class="text-[13px] font-semibold text-[#717171] uppercase tracking-wider">{{ $isRtl ? 'البريد الإلكتروني' : 'Email' }}</dt>
                <dd class="text-[15px] text-[#222]" dir="ltr">{{ $user->email ?: '—' }}</dd>
            </div>
            <div class="flex items-center justify-between" style="padding: 14px 0;">
                <dt class="text-[13px] font-semibold text-[#717171] uppercase tracking-wider">{{ $isRtl ? 'انضممت في' : 'Member since' }}</dt>
                <dd class="text-[15px] text-[#222]">{{ $user->created_at?->locale($locale)->translatedFormat('F Y') }}</dd>
            </div>
        </dl>
    </div>
@endsection
