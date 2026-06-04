@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'المدن' : 'Cities')
@section('heading', $isRtl ? 'المدن' : 'Cities')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171]">
            {{ $cities->total() }} {{ $isRtl ? 'مدينة' : 'cities' }}
        </p>
        <a href="{{ route('admin.cities.create') }}"
           class="inline-flex items-center font-semibold text-white bg-[#222] hover:bg-black"
           style="padding: 10px 16px; gap: 6px; border-radius: 12px; corner-shape: squircle; font-size: 14px;">
            <span>+</span>
            <span>{{ $isRtl ? 'إضافة مدينة' : 'Add city' }}</span>
        </a>
    </div>

    <div class="bg-white overflow-hidden"
         style="border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                <tr>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الدولة' : 'Country' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الاسم (عربي)' : 'Name (AR)' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الاسم (إنجليزي)' : 'Name (EN)' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الأحياء' : 'Areas' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($cities as $city)
                    <tr class="border-t border-[#ebebeb]">
                        <td style="padding: 14px 20px;" class="text-[#717171]">{{ $isRtl ? $city->country?->name_ar : $city->country?->name_en }}</td>
                        <td style="padding: 14px 20px;">{{ $city->name_ar }}</td>
                        <td style="padding: 14px 20px;">{{ $city->name_en }}</td>
                        <td style="padding: 14px 20px;" class="tabular-nums text-[#717171]">{{ $city->areas_count }}</td>
                        <td style="padding: 14px 20px;" class="text-end whitespace-nowrap">
                            <a href="{{ route('admin.cities.edit', $city) }}" class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                            <form method="POST" action="{{ route('admin.cities.destroy', $city) }}"
                                  class="inline" style="margin-inline-start: 14px;"
                                  onsubmit="return confirm('{{ $isRtl ? 'حذف هذه المدينة؟' : 'Delete this city?' }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 32px 20px;" class="text-center text-[#717171]">{{ $isRtl ? 'لا توجد مدن بعد.' : 'No cities yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($cities->hasPages())
        <div style="margin-top: 20px;">{{ $cities->links() }}</div>
    @endif
@endsection
