@extends('layouts.app')

@section('title', 'Calm — لوحة التحكم')

@section('body')
@php
    use App\Support\Catalog;
@endphp

<div class="min-h-screen bg-[#f7f7f7] font-arabic">
    <header class="bg-white/90 backdrop-blur border-b border-[#ebebeb] sticky top-0 z-30">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between max-w-7xl mx-auto">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
            </a>
            <span class="text-sm font-semibold text-[#222] bg-[#f7f7f7]"
                  style="padding: 8px 16px; border-radius: 999px; corner-shape: squircle; -webkit-corner-shape: squircle;">لوحة التحكم</span>
        </div>
    </header>

    <main class="px-5 sm:px-10 lg:px-20 py-10 sm:py-14 max-w-7xl mx-auto w-full">
        <div style="margin-bottom: 56px;">
            <h1 class="text-[28px] sm:text-[36px] font-bold tracking-tight text-[#222]">إحصائيات المضيفين</h1>
            <p class="text-[15px] text-[#717171]" style="margin-top: 12px;">نظرة عامة على نشاط الموقع.</p>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ([
                'المضيفون'  => $totals['hosts'],
                'المرافق'   => $totals['facilities'],
                'المميزات'  => $totals['amenities'],
                'الصور'     => $totals['images'],
            ] as $label => $value)
                <div class="bg-white border border-[#ebebeb] shadow-card hover:shadow-md transition-shadow"
                     style="padding: 40px; border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle;">
                    <div class="text-[13px] font-medium text-[#717171]">{{ $label }}</div>
                    <div class="mt-2 text-[34px] sm:text-[40px] font-bold text-[#222] leading-none">{{ number_format($value) }}</div>
                </div>
            @endforeach
        </div>

        {{-- breakdowns --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
            <div class="bg-white border border-[#ebebeb] shadow-card"
                 style="padding: 40px; border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle;">
                <h2 class="text-[17px] font-bold text-[#222]">حسب نوع المكان</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($byPlaceType as $key => $count)
                        <div class="flex items-center justify-between text-[15px]">
                            <span class="text-[#222]">{{ Catalog::placeTypeLabel($key, 'ar') }}</span>
                            <span class="font-bold text-[#222] tabular-nums">{{ $count }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-[#717171]">لا توجد بيانات بعد.</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white border border-[#ebebeb] shadow-card"
                 style="padding: 40px; border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle;">
                <h2 class="text-[17px] font-bold text-[#222]">المرافق الأكثر شيوعاً</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($topFacilities as $key => $count)
                        <div class="flex items-center justify-between text-[15px]">
                            <span class="text-[#222]">{{ Catalog::facilityLabel($key, 'ar') }}</span>
                            <span class="font-bold text-[#222] tabular-nums">{{ $count }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-[#717171]">—</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white border border-[#ebebeb] shadow-card"
                 style="padding: 40px; border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle;">
                <h2 class="text-[17px] font-bold text-[#222]">المميزات الأكثر شيوعاً</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($topAmenities as $key => $count)
                        <div class="flex items-center justify-between text-[15px]">
                            <span class="text-[#222]">{{ Catalog::amenityLabel($key, 'ar') }}</span>
                            <span class="font-bold text-[#222] tabular-nums">{{ $count }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-[#717171]">—</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- recent hosts --}}
        <div class="bg-white border border-[#ebebeb] shadow-card mt-6 overflow-hidden"
             style="border-radius: 28px; corner-shape: squircle; -webkit-corner-shape: squircle;">
            <div class="border-b border-[#ebebeb] flex items-center justify-between"
                 style="padding: 28px 40px;">
                <h2 class="text-[17px] font-bold text-[#222]">أحدث المضيفين</h2>
                <span class="text-xs text-[#717171]">آخر 25</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[14px]">
                    <thead class="bg-[#fafafa] text-[#717171]">
                        <tr>
                            <th class="text-right font-medium" style="padding: 20px 40px;">المعرّف</th>
                            <th class="text-right font-medium" style="padding: 20px 24px;">النوع</th>
                            <th class="text-right font-medium" style="padding: 20px 24px;">الجوال</th>
                            <th class="text-center font-medium" style="padding: 20px 24px;">المرافق</th>
                            <th class="text-center font-medium" style="padding: 20px 24px;">المميزات</th>
                            <th class="text-center font-medium" style="padding: 20px 24px;">الصور</th>
                            <th class="text-right font-medium" style="padding: 20px 24px;">التاريخ</th>
                            <th style="padding: 20px 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentHosts as $host)
                            <tr class="border-t border-[#ebebeb] hover:bg-[#fafafa] transition-colors">
                                <td class="font-mono text-xs text-[#222]" dir="ltr" style="padding: 20px 40px;">{{ $host->slug }}</td>
                                <td class="text-[#222]" style="padding: 20px 24px;">{{ Catalog::placeTypeLabel($host->place_type, 'ar') }}</td>
                                <td class="text-[#222]" dir="ltr" style="padding: 20px 24px;">+966 {{ $host->phone }}</td>
                                <td class="text-center text-[#222] tabular-nums" style="padding: 20px 24px;">{{ $host->facilities_count }}</td>
                                <td class="text-center text-[#222] tabular-nums" style="padding: 20px 24px;">{{ $host->amenities_count }}</td>
                                <td class="text-center text-[#222] tabular-nums" style="padding: 20px 24px;">{{ $host->images_count }}</td>
                                <td class="text-[#717171]" style="padding: 20px 24px;">{{ $host->created_at?->diffForHumans() }}</td>
                                <td class="text-left" style="padding: 20px 40px;">
                                    <a href="{{ route('property.show', ['slug' => $host->slug]) }}"
                                       target="_blank"
                                       class="inline-flex items-center text-[#222] font-semibold bg-[#f7f7f7] hover:bg-[#ebebeb] transition-colors text-[13px]"
                                       style="padding: 8px 16px; border-radius: 999px; corner-shape: squircle; -webkit-corner-shape: squircle;">
                                        عرض
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-[#717171]" style="padding: 56px 32px;">لا توجد بيانات بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
@endsection
