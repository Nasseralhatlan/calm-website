@extends('layouts.admin')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'أنواع الأماكن' : 'Place types')
@section('heading', $isRtl ? 'أنواع الأماكن' : 'Place types')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171]">{{ $placeTypes->total() }} {{ $isRtl ? 'نوع' : 'types' }}</p>
        <a href="{{ route('admin.place-types.create') }}"
           class="inline-flex items-center font-semibold text-white bg-[#222] hover:bg-black"
           style="padding: 10px 16px; gap: 6px; border-radius: 12px; font-size: 14px;">
            <span>+</span><span>{{ $isRtl ? 'إضافة نوع' : 'Add type' }}</span>
        </a>
    </div>

    <div class="bg-white overflow-hidden"
         style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                <tr>
                    <th class="text-center" style="padding: 14px 12px; width: 56px;">{{ $isRtl ? 'الأيقونة' : 'Icon' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الاسم (عربي)' : 'Name (AR)' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الاسم (إنجليزي)' : 'Name (EN)' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الأماكن' : 'Places' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($placeTypes as $pt)
                    <tr class="border-t border-[#ebebeb]">
                        <td class="text-center" style="padding: 14px 12px; font-size: 24px; line-height: 1;">{{ $pt->icon ?: '—' }}</td>
                        <td style="padding: 14px 20px;">{{ $pt->name_ar }}</td>
                        <td style="padding: 14px 20px;">{{ $pt->name_en }}</td>
                        <td style="padding: 14px 20px;" class="tabular-nums text-[#717171]">{{ $pt->places_count }}</td>
                        <td style="padding: 14px 20px;" class="text-end whitespace-nowrap">
                            <a href="{{ route('admin.place-types.edit', $pt) }}" class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                            <form method="POST" action="{{ route('admin.place-types.destroy', $pt) }}" class="inline" style="margin-inline-start: 14px;" onsubmit="return confirm('{{ $isRtl ? 'حذف هذا النوع؟' : 'Delete this type?' }}');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 32px 20px;" class="text-center text-[#717171]">{{ $isRtl ? 'لا توجد أنواع بعد.' : 'No types yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($placeTypes->hasPages())<div style="margin-top: 20px;">{{ $placeTypes->links() }}</div>@endif
@endsection
