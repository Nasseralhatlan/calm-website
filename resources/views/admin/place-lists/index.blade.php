@extends('layouts.admin')

@php
    use App\Enums\GeoStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';

    $statusPill = fn (?GeoStatus $s): array => $s === GeoStatus::Active
        ? ['bg' => '#10b981', 'dot' => '#a7f3d0']
        : ['bg' => '#9ca3af', 'dot' => '#e5e7eb'];
@endphp

@section('title', $isRtl ? 'القوائم' : 'Place lists')
@section('heading', $isRtl ? 'القوائم' : 'Place lists')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171]">{{ $lists->total() }} {{ $isRtl ? 'قائمة' : 'lists' }}</p>
        <a href="{{ route('admin.place-lists.create') }}"
           class="inline-flex items-center font-semibold text-white bg-[#222] hover:bg-black"
           style="padding: 10px 16px; gap: 6px; border-radius: 12px; font-size: 14px;">
            <span>+</span><span>{{ $isRtl ? 'إنشاء قائمة' : 'Create list' }}</span>
        </a>
    </div>

    <div class="bg-white overflow-hidden" style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] text-[#717171] tracking-wider">
                <tr>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'القائمة' : 'List' }}</th>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'الأماكن' : 'Places' }}</th>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'الترتيب' : 'Sort' }}</th>
                    <th style="padding: 14px 20px;">{{ $isRtl ? 'الحالة' : 'Status' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($lists as $list)
                    @php $sp = $statusPill($list->status); @endphp
                    <tr class="border-t border-[#ebebeb] hover:bg-[#fafafa] transition-colors">
                        <td style="padding: 14px 20px;">
                            <span class="inline-flex items-center" style="gap: 12px;">
                                <span style="font-size: 22px; line-height: 1;">{{ $list->icon ?: '📁' }}</span>
                                <span>
                                    <div class="font-medium text-[#222]">{{ $isRtl ? $list->name_ar : $list->name_en }}</div>
                                    @if($isRtl ? $list->description_ar : $list->description_en)
                                        <div class="text-[12px] text-[#717171]">{{ $isRtl ? $list->description_ar : $list->description_en }}</div>
                                    @endif
                                </span>
                            </span>
                        </td>
                        <td class="text-[#717171] tabular-nums" style="padding: 14px 20px;">{{ $list->places_count }}</td>
                        <td class="text-[#717171] tabular-nums" style="padding: 14px 20px;" dir="ltr">{{ $list->sort_order }}</td>
                        <td style="padding: 14px 20px;">
                            <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white whitespace-nowrap"
                                  style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $sp['bg'] }};">
                                <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $sp['dot'] }}; flex-shrink: 0;"></span>
                                {{ $list->status?->value ?? 'inactive' }}
                            </span>
                        </td>
                        <td class="text-end whitespace-nowrap" style="padding: 14px 20px;">
                            <a href="{{ route('admin.place-lists.edit', $list) }}" class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                            <form method="POST" action="{{ route('admin.place-lists.destroy', $list) }}" class="inline" style="margin-inline-start: 14px;" onsubmit="return confirm('{{ $isRtl ? 'حذف هذه القائمة؟' : 'Delete this list?' }}');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-[#717171]" style="padding: 32px 20px;">{{ $isRtl ? 'لا توجد قوائم بعد.' : 'No lists yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($lists->hasPages())<div style="margin-top: 20px;">{{ $lists->links() }}</div>@endif
@endsection
