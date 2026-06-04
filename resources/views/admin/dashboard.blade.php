@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', $isRtl ? 'الرئيسية' : 'Dashboard')
@section('heading', $isRtl ? 'لوحة التحكم' : 'Dashboard')

@section('main')
    @php
        $cards = [
            ['key' => 'countries',  'label_ar' => 'الدول',      'label_en' => 'Countries',  'route' => 'admin.countries.index',  'tint' => 'bg-[#F88379]'],
            ['key' => 'cities',     'label_ar' => 'المدن',      'label_en' => 'Cities',     'route' => 'admin.cities.index',     'tint' => 'bg-[#7BA9D6]'],
            ['key' => 'city_areas', 'label_ar' => 'الأحياء',    'label_en' => 'City areas', 'route' => 'admin.city-areas.index', 'tint' => 'bg-[#A5C9A5]'],
            ['key' => 'users',      'label_ar' => 'المستخدمون', 'label_en' => 'Users',      'route' => null,                     'tint' => 'bg-[#C4A5D6]'],
        ];
    @endphp

    <p class="text-[14px] text-[#717171] {{ $fa }}" style="margin-bottom: 24px;">
        {{ $isRtl ? 'مرحباً بك. هذه نظرة سريعة على بيانات المنصة.' : 'Welcome back. Here\'s a quick look at the platform.' }}
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" style="gap: 14px;">
        @foreach($cards as $card)
            @php
                $label = $isRtl ? $card['label_ar'] : $card['label_en'];
                $count = $counts[$card['key']];
            @endphp
            @if($card['route'])
                <a href="{{ route($card['route']) }}"
                   class="block bg-white hover:-translate-y-0.5 transition-all"
                   style="padding: 22px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-bold text-[#717171] uppercase tracking-[0.12em]">{{ $label }}</span>
                        <span class="block w-2 h-2 rounded-full {{ $card['tint'] }}"></span>
                    </div>
                    <div class="text-[36px] font-bold text-[#222] tabular-nums" style="margin-top: 10px; line-height: 1;">{{ $count }}</div>
                    <div class="flex items-center text-[12px] font-semibold text-[#717171] hover:text-[#222] rtl:[&_svg]:-scale-x-100" style="margin-top: 14px; gap: 4px;">
                        <span>{{ $isRtl ? 'إدارة' : 'Manage' }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </div>
                </a>
            @else
                <div class="bg-white"
                     style="padding: 22px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-bold text-[#717171] uppercase tracking-[0.12em]">{{ $label }}</span>
                        <span class="block w-2 h-2 rounded-full {{ $card['tint'] }}"></span>
                    </div>
                    <div class="text-[36px] font-bold text-[#222] tabular-nums" style="margin-top: 10px; line-height: 1;">{{ $count }}</div>
                    <div class="text-[12px] text-[#bababa] {{ $fa }}" style="margin-top: 14px;">
                        {{ $isRtl ? 'قريباً' : 'Coming soon' }}
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endsection
