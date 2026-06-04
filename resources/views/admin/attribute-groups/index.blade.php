@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'مجموعات الخصائص' : 'Attribute groups')
@section('heading', $isRtl ? 'مجموعات الخصائص' : 'Attribute groups')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171]">{{ $attributeGroups->total() }} {{ $isRtl ? 'مجموعة' : 'groups' }}</p>
        <a href="{{ route('admin.attribute-groups.create') }}"
           class="inline-flex items-center font-semibold text-white bg-[#222] hover:bg-black"
           style="padding: 10px 16px; gap: 6px; border-radius: 12px; font-size: 14px;">
            <span>+</span><span>{{ $isRtl ? 'إضافة مجموعة' : 'Add group' }}</span>
        </a>
    </div>

    <div class="bg-white overflow-hidden"
         style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                <tr>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الاسم (عربي)' : 'Name (AR)' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الاسم (إنجليزي)' : 'Name (EN)' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الخصائص' : 'Attributes' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($attributeGroups as $group)
                    <tr class="border-t border-[#ebebeb]">
                        <td style="padding: 14px 20px;">{{ $group->name_ar }}</td>
                        <td style="padding: 14px 20px;">{{ $group->name_en }}</td>
                        <td style="padding: 14px 20px;" class="tabular-nums text-[#717171]">{{ $group->attributes_count }}</td>
                        <td style="padding: 14px 20px;" class="text-end whitespace-nowrap">
                            <a href="{{ route('admin.attribute-groups.edit', $group) }}" class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                            <form method="POST" action="{{ route('admin.attribute-groups.destroy', $group) }}" class="inline" style="margin-inline-start: 14px;" onsubmit="return confirm('{{ $isRtl ? 'حذف هذه المجموعة؟' : 'Delete this group?' }}');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 32px 20px;" class="text-center text-[#717171]">{{ $isRtl ? 'لا توجد مجموعات بعد.' : 'No groups yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($attributeGroups->hasPages())
        <div style="margin-top: 20px;">{{ $attributeGroups->links() }}</div>
    @endif
@endsection
