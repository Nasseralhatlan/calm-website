@extends('layouts.admin')

@php $locale = app()->getLocale(); $isRtl = $locale === 'ar'; @endphp

@section('title', $isRtl ? 'الخصائص' : 'Attributes')
@section('heading', $isRtl ? 'الخصائص' : 'Attributes')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171]">{{ $attributes->total() }} {{ $isRtl ? 'خاصية' : 'attributes' }}</p>
        <a href="{{ route('admin.attributes.create') }}"
           class="inline-flex items-center font-semibold text-white bg-[#222] hover:bg-black"
           style="padding: 10px 16px; gap: 6px; border-radius: 12px; font-size: 14px;">
            <span>+</span><span>{{ $isRtl ? 'إضافة خاصية' : 'Add attribute' }}</span>
        </a>
    </div>

    <div class="bg-white overflow-hidden"
         style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                <tr>
                    <th class="text-center" style="padding: 14px 12px; width: 56px;">{{ $isRtl ? 'الأيقونة' : 'Icon' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المجموعة' : 'Group' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الاسم' : 'Name' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'النوع' : 'Type' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الصورة' : 'Photo' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($attributes as $attr)
                    <tr class="border-t border-[#ebebeb]">
                        <td class="text-center" style="padding: 14px 12px; font-size: 24px; line-height: 1;">{{ $attr->icon ?: '—' }}</td>
                        <td style="padding: 14px 20px;" class="text-[#717171]">{{ $isRtl ? $attr->group?->name_ar : $attr->group?->name_en }}</td>
                        <td style="padding: 14px 20px;">{{ $isRtl ? $attr->name_ar : $attr->name_en }}</td>
                        <td style="padding: 14px 20px;" class="font-mono text-[12px] text-[#717171]">{{ $attr->type->value }}</td>
                        <td style="padding: 14px 20px;" class="font-mono text-[12px] text-[#717171]">{{ $attr->photo_rule->value }}</td>
                        <td style="padding: 14px 20px;" class="text-end whitespace-nowrap">
                            <a href="{{ route('admin.attributes.edit', $attr) }}" class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                            <form method="POST" action="{{ route('admin.attributes.destroy', $attr) }}" class="inline" style="margin-inline-start: 14px;" onsubmit="return confirm('{{ $isRtl ? 'حذف هذه الخاصية؟' : 'Delete this attribute?' }}');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding: 32px 20px;" class="text-center text-[#717171]">{{ $isRtl ? 'لا توجد خصائص بعد.' : 'No attributes yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($attributes->hasPages())<div style="margin-top: 20px;">{{ $attributes->links() }}</div>@endif
@endsection
